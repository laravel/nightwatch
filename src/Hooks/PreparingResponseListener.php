<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Routing\Events\PreparingResponse;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\SensorManager;
use Laravel\Nightwatch\State\ExecutionState;
use Throwable;

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
        } catch (Throwable $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
