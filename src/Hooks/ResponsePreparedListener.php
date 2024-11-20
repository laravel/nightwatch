<?php

namespace Laravel\Nightwatch\Hooks;

use Exception;
use Illuminate\Routing\Events\ResponsePrepared;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Records\ExecutionState;
use Laravel\Nightwatch\SensorManager;

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
        } catch (Exception $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
