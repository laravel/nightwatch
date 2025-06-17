<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\CommandState;
use Throwable;

/**
 * @internal
 */
final class JobAttemptListener
{
    /**
     * @param  Core<CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(JobProcessed|JobReleasedAfterException|JobFailed $event): void
    {
        try {
            $this->nightwatch->jobAttempt($event);
        } catch (Throwable $e) {
            $this->nightwatch->report($e, handled: true);
        }
    }
}
