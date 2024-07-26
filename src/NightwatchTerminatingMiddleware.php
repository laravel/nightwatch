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
        $response = $next($request);

        if ($this->sensor->executionPhase() === ExecutionPhase::BeforeMiddleware) {
            $this->sensor->start(ExecutionPhase::AfterMiddleware);
        }

        return $response;
    }

    public function terminate(): void
    {
        if ($this->sensor->executionPhase() !== ExecutionPhase::Terminating) {
            $this->sensor->start(ExecutionPhase::Terminating);
        }
    }
}
