<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\Types\Str;
use Throwable;

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

    public function __invoke(ScheduledTaskStarting $event): void
    {
        try {
            $this->nightwatch->state->reset();
            $this->nightwatch->state->resetTraceId();
            $this->nightwatch->state->id = (string) Str::uuid();
            $this->nightwatch->state->timestamp = $this->nightwatch->clock->microtime();
        } catch (Throwable $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
