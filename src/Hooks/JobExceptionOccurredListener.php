<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\SensorManager;
use Throwable;

/**
 * @internal
 */
final class JobExceptionOccurredListener
{
    public function __construct(private SensorManager $sensor) {
        //
    }

    public function __invoke(JobExceptionOccurred $event): void
    {
        try {
            $this->sensor->exception($event->exception);
        } catch (Throwable $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
