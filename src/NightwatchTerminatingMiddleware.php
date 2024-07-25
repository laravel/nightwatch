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

    public function __invoke(Request $request, Closure $next): mixed
    {
        return $next($request);
    }

    public function terminate(): void
    {
        $this->sensor->start(ExecutionPhase::Terminating);
    }
}
