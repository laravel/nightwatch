<?php

namespace Laravel\Nightwatch\Sensors;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Bootstrap\HandleExceptions;
use Illuminate\View\ViewException;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Location;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Laravel\Nightwatch\Types\Str;
use Spatie\LaravelIgnition\Exceptions\ViewException as IgnitionViewException;
use SplFileObject;
use stdClass;
use Throwable;

use function array_is_list;
use function array_keys;
use function array_map;
use function array_push;
use function count;
use function debug_backtrace;
use function file_exists;
use function gettype;
use function hash;
use function implode;
use function is_array;
use function is_int;
use function is_readable;
use function is_string;
use function json_encode;
use function max;
use function rtrim;
use function str_starts_with;

/**
 * @internal
 */
final class ExceptionSensor
{
    public function __construct(
        private RequestState|CommandState $executionState,
        private Clock $clock,
        private Location $location,
        private Repository $config,
    ) {
        //
    }

    /**
     * @return array<mixed>
     */
    public function __invoke(Throwable $e, ?bool $handled): array
    {
        $nowMicrotime = $this->clock->microtime();
        [$file, $line] = $this->location->forException($e);
        $normalizedException = match ($e->getPrevious()) {
            null => $e,
            default => match (true) {
                $e instanceof ViewException,
                $e instanceof IgnitionViewException => $e->getPrevious(),
                default => $e,
            },
        };

        $handled ??= $this->wasManuallyReported($normalizedException);

        if (! $handled) {
            $this->executionState->exceptionPreview = $normalizedException->getMessage();
        }

        $this->executionState->exceptions++;

        return [
            'v' => 2,
            't' => 'exception',
            'timestamp' => $nowMicrotime,
            'deploy' => $this->executionState->deploy,
            'server' => $this->executionState->server,
            '_group' => hash('xxh128', $normalizedException::class.','.$normalizedException->getCode().','.$file.','.$line),
            'trace_id' => $this->executionState->trace,
            'execution_source' => $this->executionState->source,
            'execution_id' => $this->executionState->id(),
            'execution_preview' => $this->executionState->executionPreview(),
            'execution_stage' => $this->executionState->stage,
            'user' => $this->executionState->user->id(),
            'class' => Str::tinyText($normalizedException::class),
            'file' => Str::tinyText($file),
            'line' => $line ?? 0,
            'message' => Str::text($normalizedException->getMessage()),
            'code' => (string) $normalizedException->getCode(),
            'trace' => Str::mediumText($this->serializeTrace($normalizedException, (bool) $this->config->get('nightwatch.exceptions.capture_source_code', true))),
            'handled' => $handled,
            'php_version' => $this->executionState->phpVersion,
            'laravel_version' => $this->executionState->laravelVersion,
        ];
    }

    private function wasManuallyReported(Throwable $e): bool
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, limit: 20) as $frame) {
            if ($frame['function'] === 'report' && ! isset($frame['type'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Collect source code lines around the provided line.
     */
    private function collectSourceCodeLines(SplFileObject $contents, ?int $line, int $contextLines = 5): ?stdClass
    {
        if ($line === null) {
            return null;
        }

        $sourceCodeLines = new stdClass;

        $contents->seek(max(0, $line - 1 - $contextLines));

        while ($contents->key() <= $line - 1 + $contextLines && ! $contents->eof()) {
            $sourceCodeLines->{$contents->key() + 1} = rtrim($contents->fgets(), "\r\n");
        }

        return $sourceCodeLines;
    }

    /**
     * Load the source code for the provided file.
     */
    private function loadSourceCode(string $file): ?SplFileObject
    {
        $fullPath = $file;
        if (! str_starts_with($file, DIRECTORY_SEPARATOR)) {
            $basePath = rtrim($this->location->getBasePath(), DIRECTORY_SEPARATOR);
            $fullPath = $basePath.DIRECTORY_SEPARATOR.$file;
        }

        if (! file_exists($fullPath) || ! is_readable($fullPath)) {
            return null;
        }

        try {
            return new SplFileObject($fullPath);
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * @see https://github.com/php/php-src/blob/f17c2203883ddf53adfcb33d85523d11429729ab/Zend/zend_exceptions.c
     */
    private function serializeTrace(Throwable $e, bool $captureSourceLines = true): string
    {
        $userFiles = [];
        $trace = [
            // Insert the exception location as the first frame.
            // This matches the behavior of Symfony's exception renderer.
            [
                'file' => ($file = $this->location->normalizeFile($e->getFile())).':'.$e->getLine(),
                'source' => '',
            ],
        ];

        if (! $this->location->isVendorFile($file) && ! $this->location->isInternalFile($file)) {
            $userFiles[$file] = [];
            array_push($userFiles[$file], ['frameIndex' => 0, 'frameLine' => $e->getLine()]);
        }

        foreach ($e->getTrace() as $i => $frame) {
            if ($i < 2 && ($frame['class'] ?? '') === HandleExceptions::class) {
                // Skip internal frames when a PHP error has been converted to an ErrorException
                // This matches the behavior of Laravel's exception renderer.
                continue;
            }

            $file = match (true) {
                ! isset($frame['file']) => '[internal function]',
                ! is_string($frame['file']) => '[unknown file]', // @phpstan-ignore booleanNot.alwaysFalse
                default => $this->location->normalizeFile($frame['file']),
            };

            $originalFile = $file;

            if (isset($frame['line']) && is_int($frame['line'])) { // @phpstan-ignore booleanAnd.rightAlwaysTrue
                $file .= ':'.$frame['line'];
            }

            $source = '';

            if (isset($frame['class']) && is_string($frame['class'])) { // @phpstan-ignore booleanAnd.rightAlwaysTrue
                $source .= $frame['class'];
            }

            if (isset($frame['type']) && is_string($frame['type'])) { // @phpstan-ignore booleanAnd.rightAlwaysTrue
                $source .= $frame['type'];
            }

            if (isset($frame['function']) && is_string($frame['function'])) { // @phpstan-ignore booleanAnd.rightAlwaysTrue, isset.offset
                $source .= $frame['function'];
            }

            $source .= '(';

            if (isset($frame['args']) && is_array($frame['args']) && count($frame['args']) > 0) { // @phpstan-ignore booleanAnd.rightAlwaysTrue
                $args = array_map(static fn ($argument) => match (gettype($argument)) {
                    'NULL' => 'null',
                    'boolean' => 'bool',
                    'integer' => 'int',
                    'double' => 'float',
                    'array' => 'array',
                    'object' => $argument::class,
                    'resource' => 'resource',
                    'resource (closed)' => 'resource (closed)',
                    'string' => 'string',
                    'unknown type' => '[unknown]',
                }, $frame['args']);

                if (! array_is_list($args)) {
                    $args = array_map(static fn ($value, $key) => "{$key}: {$value}", $args, array_keys($args));
                }

                $source .= implode(', ', $args);
            }

            $source .= ')';

            $traceFrame = ['file' => $file, 'source' => $source];

            if (
                isset($frame['file'], $frame['line']) &&
                ! $this->location->isVendorFile($frame['file']) &&
                ! $this->location->isInternalFile($frame['file']) &&
                $originalFile !== '[internal function]' &&
                $originalFile !== '[unknown file]') {
                $userFiles[$originalFile] = $userFiles[$originalFile] ?? [];
                array_push($userFiles[$originalFile], ['frameIndex' => $i + 1, 'frameLine' => $frame['line']]);
            }

            $trace[] = $traceFrame;
        }

        if ($captureSourceLines) {
            foreach ($userFiles as $file => $frames) {
                $fileContents = $this->loadSourceCode($file);
                if ($fileContents === null) {
                    continue;
                }
                foreach ($frames as $frame) { // @phpstan-ignore foreach.emptyArray
                    $sourceCodeLines = $this->collectSourceCodeLines($fileContents, $frame['frameLine']);
                    if ($sourceCodeLines === null) {
                        continue;
                    }

                    $trace[$frame['frameIndex']]['code'] = $sourceCodeLines;
                }
            }
        }

        return json_encode($trace, flags: JSON_THROW_ON_ERROR);
    }
}
