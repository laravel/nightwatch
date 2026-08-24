<?php

namespace Laravel\Nightwatch\Sensors;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Bootstrap\HandleExceptions;
use Illuminate\View\ViewException;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Location;
use Laravel\Nightwatch\Records\Exception;
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
use function count;
use function debug_backtrace;
use function gettype;
use function hash;
use function implode;
use function is_array;
use function is_int;
use function is_string;
use function json_encode;
use function max;
use function rtrim;
use function str_replace;

/**
 * @internal
 */
final class ExceptionSensor
{
    /**
     * @var array<string, SplFileObject|null>
     */
    private array $fileObjects = [];

    private int $capturedCodeFrames = 0;

    public function __construct(
        private RequestState|CommandState $executionState,
        private Clock $clock,
        private Location $location,
        private bool $captureSourceCode,
        private ExceptionHandler $exceptionHandler,
    ) {
        //
    }

    /**
     * @return ?array{0: Exception, 1: callable(): array<mixed>}
     */
    public function __invoke(Throwable $e, ?bool $handled): ?array
    {
        if ($handled !== null && ! $this->exceptionHandler->shouldReport($e)) {
            return null;
        }

        $nowMicrotime = $this->clock->microtime();
        [$file, $line] = $this->location->forException($e);
        $normalizedException = $e;

        while (($normalizedException instanceof ViewException || $normalizedException instanceof IgnitionViewException) && $normalizedException->getPrevious() !== null) {
            $normalizedException = $normalizedException->getPrevious();
        }

        $handled ??= $this->wasManuallyReported($normalizedException);

        if (! $handled) {
            $this->executionState->exceptionPreview = $this->location->isSerializedClosureFile($file)
                ? str_replace($file, $this->location->hashSerializedClosure($file), $normalizedException->getMessage())
                : $normalizedException->getMessage();
        }

        return [
            $record = new Exception(
                class: $normalizedException::class,
                message: $normalizedException->getMessage(),
                code: $normalizedException->getCode(),
                file: $file,
                line: $line,
                handled: $handled,
            ),
            function () use ($nowMicrotime, $record, $normalizedException) {
                $this->executionState->exceptions++;
                $file = $this->location->hashSerializedClosure($record->file);

                return [
                    'v' => 3,
                    't' => 'exception',
                    'timestamp' => $nowMicrotime,
                    'deploy' => $this->executionState->deploy,
                    'server' => $this->executionState->server,
                    '_group' => hash('xxh128', $record->class.','.$record->code.','.$file.','.$record->line),
                    'trace_id' => $this->executionState->trace,
                    'execution_source' => $this->executionState->source,
                    'execution_id' => $this->executionState->id(),
                    'execution_preview' => $this->executionState->executionPreview(),
                    'execution_stage' => $this->executionState->stage,
                    'user' => $this->executionState->user->id(),
                    'class' => Str::text($record->class),
                    'file' => Str::text($file),
                    'line' => $record->line ?? 0,
                    // $file has had any serialized closure file paths replaced with their hash
                    // Any exception messages that contain the serialized closure file path
                    // will have it replaced with the hash
                    'message' => Str::text(str_replace($record->file, $file, $record->message)),
                    'code' => Str::text((string) $record->code),
                    'trace' => Str::mediumText($this->serializeTrace($normalizedException)),
                    'handled' => $record->handled,
                    'php_version' => $this->executionState->phpVersion,
                    'laravel_version' => $this->executionState->laravelVersion,
                ];
            },
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
     * @see https://github.com/php/php-src/blob/f17c2203883ddf53adfcb33d85523d11429729ab/Zend/zend_exceptions.c
     */
    private function serializeTrace(Throwable $e): string
    {
        $trace = [
            // Insert the exception location as the first frame.
            // This matches the behavior of Symfony's exception renderer.
            [
                'file' => $this->location->hashSerializedClosure(
                    $this->location->normalizeFilePath($e->getFile()).':'.$e->getLine(),
                ),
                'source' => '',
                'code' => $this->fetchSourceCode($e->getFile(), $e->getLine()),
            ],
        ];

        $previousSerializedClosureFile = $this->location->isSerializedClosureFile($e->getFile())
            ? $e->getFile()
            : null;

        foreach ($e->getTrace() as $i => $frame) {
            if ($i < 2 && ($frame['class'] ?? '') === HandleExceptions::class) {
                // Skip internal frames when a PHP error has been converted to an ErrorException
                // This matches the behavior of Laravel's exception renderer.
                continue;
            }

            $file = match (true) {
                ! isset($frame['file']) => '[internal function]',
                ! is_string($frame['file']) => '[unknown file]', // @phpstan-ignore booleanNot.alwaysFalse
                default => $this->location->hashSerializedClosure($this->location->normalizeFilePath($frame['file'])),
            };

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

            if ($previousSerializedClosureFile) {
                $source = str_replace($previousSerializedClosureFile, $this->location->hashSerializedClosure($previousSerializedClosureFile), $source);
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

            $trace[] = [
                'file' => $file,
                'source' => $source,
                'code' => $this->fetchSourceCode($frame['file'] ?? null, $frame['line'] ?? null),
            ];

            $previousSerializedClosureFile = $this->location->isSerializedClosureFile($frame['file'] ?? '')
                ? $frame['file']
                : null;
        }

        $this->fileObjects = [];
        $this->capturedCodeFrames = 0;

        return json_encode($trace, flags: JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function fetchSourceCode(mixed $file, mixed $line, int $context = 5): ?stdClass
    {
        if (! $this->captureSourceCode || $this->capturedCodeFrames >= 10) {
            return null;
        }

        if (! is_string($file) || ! is_int($line)) {
            return null;
        }

        if (! $this->location->isApplicationFile($file) && ! $this->location->isSerializedClosureFile($file)) {
            return null;
        }

        $fileObject = $this->fileObjects[$file] ??= $this->getFileObject($file);

        if ($fileObject === null) {
            return null;
        }

        try {
            $fileObject->seek(max(0, $line - 1 - $context));

            $code = new stdClass;

            while ($fileObject->key() <= $line - 1 + $context && ! $fileObject->eof()) {
                $code->{$fileObject->key() + 1} = rtrim($fileObject->fgets(), "\r\n");
            }

            $this->capturedCodeFrames++;

            return $code;
        } catch (Throwable $e) {
            Nightwatch::unrecoverableExceptionOccurred($e);

            return null;
        }
    }

    /**
     * Get a file object for the given file path.
     */
    private function getFileObject(string $file): ?SplFileObject
    {
        try {
            return new SplFileObject($file);
        } catch (Throwable $e) {
            Nightwatch::unrecoverableExceptionOccurred($e);

            return null;
        }
    }
}
