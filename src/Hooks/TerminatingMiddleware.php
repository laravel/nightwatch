<?php

namespace Laravel\Nightwatch\Hooks;

use Closure;
use Illuminate\Http\Request;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\SensorManager;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class TerminatingMiddleware
{
    public function __construct(
        private SensorManager $sensor,
    ) {
        //
    }

    public function handle(Request $request, Closure $next): mixed
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            $this->sensor->stage(ExecutionStage::Terminating);
        } catch (Throwable $e) {
            $this->sensor->exception($e);
        }
    }
}
