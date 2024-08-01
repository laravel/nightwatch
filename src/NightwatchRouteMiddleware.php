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
        $this->sensor->start(ExecutionStage::Action);

        $response = $next($request);

        $this->sensor->start(ExecutionStage::AfterMiddleware);

        return $response;
    }
}
