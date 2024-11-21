<?php

namespace Laravel\Nightwatch\Hooks;

use Exception;
use Illuminate\Routing\Events\PreparingResponse;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Records\ExecutionState;
use Laravel\Nightwatch\SensorManager;

final class PreparingResponseListener
{
    public function __construct(private SensorManager $sensor, private ExecutionState $executionState)
    {
        //
    }

    public function __invoke(PreparingResponse $event): void
    {
        try {
            if ($this->executionState->stage === ExecutionStage::Action) {
                $this->sensor->stage(ExecutionStage::Render);
            }
        } catch (Exception $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
