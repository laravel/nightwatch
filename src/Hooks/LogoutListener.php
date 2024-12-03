<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Auth\Events\Logout;
use Laravel\Nightwatch\UserProvider;

final class LogoutListener
{
    public function __construct(private UserProvider $userProvider)
    {
        //
    }

    public function __invoke(Logout $event): void
    {
        if ($event->user === null) {
            return;
        }

        $this->userProvider->remember($event->user);
    }
}
