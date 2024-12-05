<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\SensorManager;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Throwable;

final class ReportableHandler
{
    public function __construct(
        private SensorManager $sensor,
        private RequestState|CommandState $executionState,
    ) {
        //
    }

    public function __invoke(Throwable $exception): void
    {
        try {
            if ($this->executionState->source === 'job') {
                return;
            }

            $this->sensor->exception($exception);
        } catch (Throwable $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
