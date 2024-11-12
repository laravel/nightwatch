<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Foundation\Events\Terminating;
use Illuminate\Routing\Events\RouteMatched;

use function array_unshift;
use function class_exists;

class RouteMatchedListener
{
    public function __invoke(RouteMatched $event): void
    {
        $middleware = $event->route->action['middleware'] ?? [];

        $middleware[] = RouteMiddleware::class; // TODO ensure adding these is not a memory leak in Octane (event though Laravel will make sure they are unique)

        if (! class_exists(Terminating::class)) {
            array_unshift($middleware, TerminatingMiddleware::class);
        }

        $event->route->action['middleware'] = $middleware;
    }
}
