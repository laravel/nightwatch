<?php

namespace Laravel\Nightwatch\Hooks;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\SensorManager;
use Laravel\Nightwatch\State\RequestState;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class TerminatingMiddleware
{
    public function __construct(
        private SensorManager $sensor,
        private RequestState $requestState,
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
            if ($this->requestState->stage !== ExecutionStage::Terminating) {
                $this->sensor->stage(ExecutionStage::Terminating);
            }
        } catch (Throwable $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
