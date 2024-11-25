<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\SensorManager;
use Throwable;

/**
 * @internal
 */
final class NotificationSentListener
{
    public function __construct(private SensorManager $sensor)
    {
        //
    }

    public function __invoke(NotificationSent $event): void
    {
        try {
            $this->sensor->notification($event);
        } catch (Throwable $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
