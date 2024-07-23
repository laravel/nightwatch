<?php

namespace Laravel\Nightwatch;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NightwatchRouteMiddleware
{
    public function __construct(private SensorManager $sensor)
    {
        //
    }

    public function __invoke(Request $request, Closure $next): mixed
    {
        $this->sensor->startPhase(LifecyclePhase::Main);

        $response = $next($request);

        $this->sensor->startPhase(LifecyclePhase::RouteAfterMiddleware);

        return $response;
    }

    public function terminate(): void
    {
        $this->sensor->startPhase(LifecyclePhase::Terminate);
    }
}
