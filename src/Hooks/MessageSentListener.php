<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\SensorManager;
use Throwable;

/**
 * @internal
 */
final class MessageSentListener
{
    public function __construct(private SensorManager $sensor)
    {
        //
    }

    public function __invoke(MessageSent $event): void
    {
        try {
            $this->sensor->mail($event);
        } catch (Throwable $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
