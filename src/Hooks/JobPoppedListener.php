<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Queue\Events\JobPopped;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\State\CommandState;
use Throwable;

/**
 * @internal
 */
final class JobPoppedListener
{
    public function __construct(private CommandState $state)
    {
        //
    }

    public function __invoke(JobPopped $event): void
    {
        try {
            $this->state->prepareForNextJobAttempt();
        } catch (Throwable $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
