<?php

namespace Laravel\Nightwatch\Hooks;

use Exception;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\SensorManager;

/**
 * @internal
 */
final class JobAttemptListener
{
    public function __construct(private SensorManager $sensor)
    {
        //
    }

    public function __invoke(JobProcessing|JobProcessed|JobReleasedAfterException|JobFailed $event): void
    {
        try {
            $this->sensor->jobAttempt($event);
        } catch (Exception $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
