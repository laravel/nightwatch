<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\JobQueueing;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Throwable;

/**
 * @internal
 */
final class QueuedJobListener
{
    /**
     * @param  Core<RequestState|CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(JobQueueing|JobQueued $event): void
    {
        try {
            $this->nightwatch->sensor->queuedJob($event);
        } catch (Throwable $e) {
            $this->nightwatch->report($e);
        }
    }
}
