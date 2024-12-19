<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Notifications\Events\NotificationSent;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Throwable;

/**
 * @internal
 */
final class NotificationSentListener
{
    /**
     * @param  Core<RequestState>|Core<CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(NotificationSent $event): void
    {
        try {
            $this->nightwatch->sensor->notification($event);
        } catch (Throwable $e) {
            $this->nightwatch->report($e);
        }
    }
}
