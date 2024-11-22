<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Routing\Events\ResponsePrepared;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Records\ExecutionState;
use Laravel\Nightwatch\SensorManager;
use Throwable;

final class ResponsePreparedListener
{
    public function __construct(private SensorManager $sensor, private ExecutionState $executionState)
    {
        //
    }

    public function __invoke(ResponsePrepared $event): void
    {
        try {
            if ($this->executionState->stage === ExecutionStage::Render) {
                $this->sensor->stage(ExecutionStage::AfterMiddleware);
            }
        } catch (Throwable $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
