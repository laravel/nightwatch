<?php

namespace Laravel\Nightwatch;

use Closure;
use Exception;
use Illuminate\Http\Request;

final class NightwatchRouteMiddleware
{
    public function __construct(private SensorManager $sensor)
    {
        //
    }

    public function __invoke(Request $request, Closure $next): mixed
    {
        try {
            $this->sensor->stage(ExecutionStage::Action);
        } catch (Exception $e) {
            //
        }

        return $next($request);
    }
}
