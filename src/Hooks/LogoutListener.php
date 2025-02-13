<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Auth\Events\Logout;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Throwable;

/**
 * @internal
 */
final class LogoutListener
{
    /**
     * @param  Core<RequestState|CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(Logout $event): void
    {
        try {
            if ($event->user !== null) {
                $this->nightwatch->state->user->remember($event->user);
            }
        } catch (Throwable $e) {
            $this->nightwatch->report($e);
        }
    }
}
