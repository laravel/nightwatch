<?php

namespace Laravel\Nightwatch\Hooks;

use Exception;
use Illuminate\Queue\Events\JobQueued;
use Laravel\Nightwatch\SensorManager;

/**
 * @internal
 */
final class JobQueuedListener
{
    public function __construct(private SensorManager $sensor)
    {
        //
    }

    public function __invoke(JobQueued $event): void
    {
        try {
            $this->sensor->queuedJob($event);
        } catch (Exception $e) {
            //
        }
    }
}
