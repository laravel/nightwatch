<?php

namespace Laravel\Nightwatch\Hooks;

use Closure;
use Illuminate\Http\Request;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\State\RequestState;
use Throwable;

/**
 * @internal
 */
final class RouteMiddleware
{
    /**
     * @param  Core<RequestState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function handle(Request $request, Closure $next): mixed
    {
        try {
            $this->nightwatch->stage(ExecutionStage::Action);
        } catch (Throwable $e) {
            $this->nightwatch->report($e, handled: true);
        }

        $response = $next($request);

        // If an exception occurs in the action phase, the usual
        // ResponsePrepared event is not fired. This fallback
        // ensures that we go to the AfterMiddleware stage.
        try {
            $this->nightwatch->stage(ExecutionStage::AfterMiddleware);
        } catch (Throwable $e) {
            $this->nightwatch->report($e, handled: true);
        }

        return $response;
    }
}
