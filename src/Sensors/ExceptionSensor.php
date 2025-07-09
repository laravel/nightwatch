<?php

namespace Laravel\Nightwatch\Sensors;

use Illuminate\Contracts\Config\Repository;
use Illuminate\View\ViewException;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Location;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Laravel\Nightwatch\Types\Str;
use Spatie\LaravelIgnition\Exceptions\ViewException as IgnitionViewException;
use Throwable;

use function array_is_list;
use function array_keys;
use function array_map;
use function count;
use function debug_backtrace;
use function file_exists;
use function file_get_contents;
use function gettype;
use function hash;
use function implode;
use function is_array;
use function is_int;
use function is_readable;
use function is_string;
use function json_encode;
use function max;
use function min;
use function preg_split;
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

        $captureSourceLines = $this->config->get('nightwatch.exceptions.capture_source_lines', true);
        $sourceLines = $captureSourceLines ? $this->collectSourceCodeLines($file, $line) : null;

        $payload = [
            'v' => 1,
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
            'trace' => Str::mediumText($this->serializeTrace($normalizedException, $captureSourceLines)),
            'handled' => $handled,
            'php_version' => $this->executionState->phpVersion,
            'laravel_version' => $this->executionState->laravelVersion,
        ];

        if ($sourceLines !== null) {
            $payload['source_lines'] = $sourceLines;
        }

        return $payload;
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
     * Collect source code lines around the exception location.
     *
     * @return array<string, mixed>|null
     */
    private function collectSourceCodeLines(string $file, ?int $line, int $contextLines = 5): ?array
    {
        if ($line === null) {
            return null;
        }

        // Convert normalized file path back to full path for reading
        $fullPath = $file;
        if (! str_starts_with($file, DIRECTORY_SEPARATOR)) {
            // The file is normalized (relative to base path), so we need to add the base path back
            $basePath = rtrim($this->location->getBasePath(), DIRECTORY_SEPARATOR);
            $fullPath = $basePath.DIRECTORY_SEPARATOR.$file;
        }

        if (! file_exists($fullPath) || ! is_readable($fullPath)) {
            return null;
        }

        try {
            $contents = file_get_contents($fullPath);
            if ($contents === false) {
                return null;
            }

            $lines = preg_split('/\r\n|\r|\n/', $contents);
            if ($lines === false) {
                return null;
            }

            $totalLines = count($lines);
            $startLine = max(1, $line - $contextLines);
            $endLine = min($totalLines, $line + $contextLines);

            $sourceLines = [];
            for ($i = $startLine; $i <= $endLine; $i++) {
                $sourceLines[] = [
                    'line' => $i,
                    'code' => $lines[$i - 1] ?? '',
                    'is_exception_line' => $i === $line,
                ];
            }

            return [
                'file' => $file,
                'line' => $line,
                'start_line' => $startLine,
                'end_line' => $endLine,
                'total_lines' => $totalLines,
                'lines' => $sourceLines,
            ];
        } catch (Throwable $e) {
            // If we can't read the file for any reason, return null
            return null;
        }
    }

    /**
     * @see https://github.com/php/php-src/blob/f17c2203883ddf53adfcb33d85523d11429729ab/Zend/zend_exceptions.c
     */
    private function serializeTrace(Throwable $e, bool $captureSourceLines = true): string
    {
        $trace = [];
        $frameIndex = 0;

        foreach ($e->getTrace() as $frame) {
            $file = match (true) {
                ! isset($frame['file']) => '[internal function]',
                ! is_string($frame['file']) => '[unknown file]', // @phpstan-ignore booleanNot.alwaysFalse
                default => $this->location->normalizeFile($frame['file']),
            };

            $originalFile = $file;
            $frameLine = null;

            if (isset($frame['line']) && is_int($frame['line'])) { // @phpstan-ignore booleanAnd.rightAlwaysTrue
                $file .= ':'.$frame['line'];
                $frameLine = $frame['line'];
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

            // Add source code lines for the first few frames if feature is enabled
            if ($captureSourceLines && $frameIndex < 3 && $originalFile !== '[internal function]' && $originalFile !== '[unknown file]') {
                $sourceLines = $this->collectSourceCodeLines($originalFile, $frameLine);
                if ($sourceLines !== null) {
                    $traceFrame['source_lines'] = $sourceLines;
                }
            }

            $trace[] = $traceFrame;
            $frameIndex++;
        }

        return json_encode($trace, flags: JSON_THROW_ON_ERROR);
    }
}
