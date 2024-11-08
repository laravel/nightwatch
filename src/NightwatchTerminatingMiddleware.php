<?php

namespace Laravel\Nightwatch;

use Closure;
use Exception;
use Illuminate\Http\Request;
use Laravel\Nightwatch\Records\ExecutionState;

final class NightwatchTerminatingMiddleware
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

    public function terminate(): void
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
