<?php

namespace Laravel\Nightwatch\Sensors;

use Illuminate\Support\Str;
use Illuminate\View\ViewException;
use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Location;
use Laravel\Nightwatch\Records\Exception;
use Laravel\Nightwatch\Records\ExecutionState;
use Laravel\Nightwatch\UserProvider;
use Spatie\LaravelIgnition\Exceptions\ViewException as IgnitionViewException;
use Throwable;

use function array_map;
use function debug_backtrace;
use function gettype;
use function hash;
use function is_array;
use function is_int;
use function is_string;
use function json_encode;

/**
 * @internal
 */
final class ExceptionSensor
{
    public function __construct(
        private Clock $clock,
        private ExecutionState $executionState,
        private Location $location,
        private RecordsBuffer $recordsBuffer,
        private UserProvider $user,
        private string $basePath,
    ) {
        //
    }

    public function __invoke(Throwable $e): void
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

        $this->executionState->exceptions++;

        $this->recordsBuffer->writeException(new Exception(
            timestamp: $nowMicrotime,
            deploy: $this->executionState->deploy,
            server: $this->executionState->server,
            _group: hash('md5', $normalizedException::class.','.$normalizedException->getCode().','.$file.','.$line),
            trace_id: $this->executionState->trace,
            execution_context: $this->executionState->context,
            execution_id: $this->executionState->id,
            execution_stage: $this->executionState->stage,
            user: $this->user->id(),
            class: $normalizedException::class,
            file: $file,
            line: $line ?? 0,
            message: $normalizedException->getMessage(),
            code: $normalizedException->getCode(),
            trace: $this->parseTrace($normalizedException),
            handled: $this->wasManuallyReported($normalizedException),
        ));
    }

    private function wasManuallyReported(Throwable $e): bool
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            if ($frame['function'] === 'report' && ! isset($frame['type'])) {
                return true;
            }
        }

        return false;
    }

    private function parseTrace(Throwable $e): string
    {
        $trace = [];

        foreach ($e->getTrace() as $frame) {
            $trace[] = [
                'file' => match (true) {
                    ! isset($frame['file']) => '[internal function]',
                    ! is_string($frame['file']) => '[unknown file]',
                    default => $frame['file'],
                },
                'line' => isset($frame['line']) && is_int($frame['line'])
                    ? $frame['line']
                    : 0,
                'class' => isset($frame['class']) && is_string($frame['class'])
                    ? $frame['class']
                    : '',
                'type' => isset($frame['type']) && is_string($frame['type'])
                    ? $frame['type']
                    : '',
                'function' => isset($frame['function']) && is_string($frame['function'])
                    ? $frame['function']
                    : '[unknown]',
                'args' => isset($frame['args']) && is_array($frame['args'])
                    ? array_map(fn ($argument) => match (gettype($argument)) {
                        'NULL' => 'null',
                        'boolean' => $argument ? 'true' : 'false',
                        'integer', 'double' => (string) $argument,
                        'array' => 'array',
                        'object' => $argument::class,
                        'resource', 'resource (closed)' => 'resource',
                        'string' => Str::limit($argument, 15),
                        'unknown type' => '[unknown]',
                    }, $frame['args'])
                    : [],
            ];
        }

        return json_encode($trace, flags: JSON_THROW_ON_ERROR);
    }
}
