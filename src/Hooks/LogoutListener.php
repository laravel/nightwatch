<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\State\RequestState;
use Throwable;

final class LogoutListener
{
    public function __construct(private RequestState $requestState)
    {
        //
    }

    public function __invoke(Logout $event): void
    {
        try {
            if ($event->user !== null) {
                $this->requestState->user->remember($event->user);
            }
        } catch (Throwable $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
