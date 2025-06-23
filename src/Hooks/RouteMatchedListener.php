<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Routing\Events\RouteMatched;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\RequestState;
use Throwable;

/**
 * @internal
 */
final class RouteMatchedListener
{
    /**
     * @param  Core<RequestState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(RouteMatched $event): void
    {
        try {
            $this->nightwatch->attachMiddlewareToRoute($event->route);
        } catch (Throwable $e) {
            $this->nightwatch->report($e, handled: true);
        }
    }
}
