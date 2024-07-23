<?php

namespace Laravel\Nightwatch;

use Closure;
use Illuminate\Http\Request;

final class NightwatchRouteMiddleware
{
    public function __construct(private SensorManager $sensor)
    {
        //
    }

    public function __invoke(Request $request, Closure $next): mixed
    {
        $this->sensor->startPhase(ExecutionPhase::Main);

        $response = $next($request);

        $this->sensor->startPhase(ExecutionPhase::RouteAfterMiddleware);

        return $response;
    }

    public function terminate(): void
    {
        $this->sensor->startPhase(ExecutionPhase::Terminate);
    }
}
