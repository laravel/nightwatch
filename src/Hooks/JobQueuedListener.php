<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\SensorManager;
use Throwable;

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
        } catch (Throwable $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
