<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Foundation\Events\Terminating;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Log;
use Throwable;

use function array_unshift;
use function class_exists;

final class RouteMatchedListener
{
    public function __invoke(RouteMatched $event): void
    {
        try {
            /** @var array<string> */
            $middleware = $event->route->middleware();

            // TODO check this isn't a memory leak in Octane. When checking this one
            // remember that Laravel will automaticall deduplicate middleware, so you
            // will need to manually inspect the middleware array.
            $middleware[] = RouteMiddleware::class;

            if (! class_exists(Terminating::class)) {
                // TODO check this isn't a memory leak in octane.
                array_unshift($middleware, TerminatingMiddleware::class);
            }

            $event->route->action['middleware'] = $middleware;
        } catch (Throwable $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
