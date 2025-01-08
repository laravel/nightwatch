<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Mail\Events\MessageSent;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Throwable;

/**
 * @internal
 */
final class MessageSentListener
{
    /**
     * @param  Core<RequestState|CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(MessageSent $event): void
    {
        try {
            $this->nightwatch->sensor->mail($event);
        } catch (Throwable $e) {
            $this->nightwatch->report($e);
        }
    }
}
