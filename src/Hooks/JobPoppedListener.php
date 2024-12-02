<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Queue\Events\JobPopped;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\SensorManager;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\Types\Str;
use Throwable;

/**
 * @internal
 */
final class JobPoppedListener
{
    public function __construct(private SensorManager $sensor, private CommandState $state)
    {
        //
    }

    public function __invoke(JobPopped $event): void
    {
        try {
            // Reset the id, timestamp, and counters in the CommandState
            $this->state->id = (string) Str::uuid();

            // Or should we call `$sensor->prepareForNextInvocation()` here?
        } catch (Throwable $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
