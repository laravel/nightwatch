<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Mail\Events\MessageSent;
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
            $this->sensor->exception($e);
        }
    }
}
