<?php

namespace Laravel\Nightwatch\Hooks;

use Exception;
use Illuminate\Foundation\Events\Terminating;
use Illuminate\Routing\Events\RouteMatched;
use Laravel\Nightwatch\Hooks\RouteMiddleware;
use Laravel\Nightwatch\Hooks\TerminatingMiddleware;

use function array_unshift;
use function class_exists;

class RouteMatchedHook
{
    public function __invoke(RouteMatched $event): void
    {
        try {
            $middleware = $event->route->action['middleware'] ?? [];

            $middleware[] = RouteMiddleware::class; // TODO ensure adding these is not a memory leak in Octane (event though Laravel will make sure they are unique)

            if (! class_exists(Terminating::class)) {
                array_unshift($middleware, TerminatingMiddleware::class);
            }

            $event->route->action['middleware'] = $middleware;
        } catch (Exception $e) {
            //
        }
    }
}
