<?php

namespace Laravel\Nightwatch\Sensors;

use Illuminate\View\ViewException;
use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Location;
use Laravel\Nightwatch\Records\Exception;
use Laravel\Nightwatch\Records\ExecutionState;
use Laravel\Nightwatch\UserProvider;
use Spatie\LaravelIgnition\Exceptions\ViewException as IgnitionViewException;
use Throwable;

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
                $e instanceof IgnitionViewException => $e->getPrevious() ?? $e,
                default => $e,
            },
        };

        $this->executionState->exceptions++;

        $this->recordsBuffer->writeException(new Exception(
            timestamp: $nowMicrotime,
            deploy: $this->executionState->deploy,
            server: $this->executionState->server,
            _group: hash('md5', implode(',', [$normalizedException::class, $normalizedException->getCode(), $file, $line])),
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
            trace: $normalizedException->getTraceAsString(),
            handled: $this->wasManuallyReported($normalizedException),
        ));
    }

    protected function wasManuallyReported(Throwable $e): bool
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            if (($frame['function'] ?? null) === 'report' && ! isset($frame['type'])) {
                return true;
            }
        }

        return false;
    }
}
