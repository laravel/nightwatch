<?php

namespace Laravel\Nightwatch\Hooks;

use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\SensorManager;

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
        } catch (Exception $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }

        return $next($request);
    }
}
