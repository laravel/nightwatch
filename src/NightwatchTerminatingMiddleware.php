<?php

namespace Laravel\Nightwatch;

use Closure;
use Illuminate\Http\Request;

final class NightwatchTerminatingMiddleware
{
    public function __construct(private SensorManager $sensor)
    {
        //
    }

    public function handle(Request $request, Closure $next): mixed
    {
        return $next($request);
    }

    public function terminate(): void
    {
        if ($this->sensor->executionStage() !== ExecutionStage::Terminating) {
            $this->sensor->stage(ExecutionStage::Terminating);
        }
    }
}
