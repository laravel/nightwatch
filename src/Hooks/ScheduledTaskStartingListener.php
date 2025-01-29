<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\Types\Str;
use Throwable;

use function memory_reset_peak_usage;

/**
 * @internal
 */
final class ScheduledTaskStartingListener
{
    /**
     * @param  Core<CommandState>  $nightwatch
     */
    public function __construct(private Core $nightwatch)
    {
        //
    }

    /**
     * Reset state for the current scheduled task execution.
     * Since `schedule:run` executes multiple tasks sequentially,
     * we need to clear previous task data to avoid metric pollution.
     */
    public function __invoke(ScheduledTaskStarting $event): void
    {
        try {
            $this->nightwatch->state->reset();
            $this->nightwatch->state->resetTraceId();
            $this->nightwatch->state->setId((string) Str::uuid());
            $this->nightwatch->state->timestamp = $this->nightwatch->clock->microtime();
            memory_reset_peak_usage();
        } catch (Throwable $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
