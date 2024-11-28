<?php

namespace Laravel\Nightwatch\Hooks;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\SensorManager;
use Laravel\Nightwatch\State\ExecutionState;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

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
        } catch (Throwable $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
