<?php

namespace Laravel\Nightwatch\Hooks;

use Closure;
use Exception;
use Illuminate\Http\Request;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\SensorManager;

final class RouteMiddleware
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
