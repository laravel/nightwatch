<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\State\CommandState;
use Throwable;

/**
 * @internal
 */
final class ScheduledTaskStartingListener
{
    public function __construct(private CommandState $executionState)
    {
        //
    }

    public function __invoke(ScheduledTaskStarting $event): void
    {
        try {
            $this->executionState->resetTraceId();
            $this->executionState->resetTimestamp();
            $this->executionState->prepareForNextExecution();
        } catch (Throwable $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
