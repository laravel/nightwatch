<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Foundation\Events\Terminating;
use Illuminate\Routing\Events\RouteMatched;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\RequestState;
use Laravel\Nightwatch\Supports;
use Throwable;

use function array_unshift;

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
            /** @var array<string> */
            $middleware = $event->route->middleware();

            /**
             * @see \Laravel\Nightwatch\ExecutionStage::Action
             *
             * TODO check this isn't a memory leak in Octane. When checking this one
             * remember that Laravel will automaticall deduplicate middleware, so you
             * will need to manually inspect the middleware array.
             */
            $middleware[] = RouteMiddleware::class;

            if (! Supports::$terminatingEvent) {
                /**
                 * @see \Laravel\Nightwatch\ExecutionStage::Terminating
                 *
                 * TODO check this isn't a memory leak in octane.
                 */
                array_unshift($middleware, TerminatingMiddleware::class);
            }

            $event->route->action['middleware'] = $middleware;
        } catch (Throwable $e) {
            $this->nightwatch->report($e);
        }
    }
}
