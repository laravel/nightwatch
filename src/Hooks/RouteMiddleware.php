<?php

namespace Laravel\Nightwatch\Hooks;

use Closure;
use Illuminate\Http\Request;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\SensorManager;
use Throwable;

final class RouteMiddleware
{
    public function __construct(private SensorManager $sensor)
    {
        //
    }

    public function handle(Request $request, Closure $next): mixed
    {
        try {
            $this->sensor->stage(ExecutionStage::Action);
        } catch (Throwable $e) {
            $this->sensor->exception($e);
        }

        return $next($request);
    }
}
