<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\UserProvider;
use Throwable;

final class LogoutListener
{
    public function __construct(private UserProvider $userProvider)
    {
        //
    }

    public function __invoke(Logout $event): void
    {
        try {
            if ($event->user === null) {
                return;
            }

            $this->userProvider->remember($event->user);
        } catch (Throwable $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
