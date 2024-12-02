<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Queue\Events\JobPopped;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\SensorManager;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\Types\Str;
use Throwable;

/**
 * @internal
 */
final class JobPoppedListener
{
    public function __construct(private SensorManager $sensor, private CommandState $state, private Clock $clock)
    {
        //
    }

    public function __invoke(JobPopped $event): void
    {
        try {
            $this->state->id = (string) Str::uuid();
            $this->state->timestamp = $this->clock->microtime();
        } catch (Throwable $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
