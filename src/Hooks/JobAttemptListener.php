<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\SensorManager;
use Throwable;

/**
 * @internal
 */
final class JobAttemptListener
{
    public function __construct(private SensorManager $sensor)
    {
        //
    }

    public function __invoke(JobProcessed|JobReleasedAfterException|JobFailed $event): void
    {
        try {
            $this->sensor->jobAttempt($event);
        } catch (Throwable $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
