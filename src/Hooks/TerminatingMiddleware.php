<?php

namespace Laravel\Nightwatch\Hooks;

use Closure;
use Exception;
use Illuminate\Http\Request;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Records\ExecutionState;
use Laravel\Nightwatch\SensorManager;
use Symfony\Component\HttpFoundation\Response;

final class TerminatingMiddleware
{
    public function __construct(
        private SensorManager $sensor,
        private ExecutionState $executionState,
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
            if ($this->executionState->stage !== ExecutionStage::Terminating) {
                $this->sensor->stage(ExecutionStage::Terminating);
            }
        } catch (Exception $e) {
            //
        }
    }
}
