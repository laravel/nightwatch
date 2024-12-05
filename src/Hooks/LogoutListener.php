<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Auth\Events\Logout;
use Laravel\Nightwatch\State\RequestState;

final class LogoutListener
{
    public function __construct(private RequestState $requestState)
    {
        //
    }

    public function __invoke(Logout $event): void
    {
        if ($event->user !== null) {
            $this->requestState->user->remember($event->user);
        }
    }
}
