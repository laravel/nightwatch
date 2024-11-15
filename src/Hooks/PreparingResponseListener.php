<?php

namespace Laravel\Nightwatch\Hooks;

use Exception;
use Illuminate\Routing\Events\PreparingResponse;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Records\ExecutionState;
use Laravel\Nightwatch\SensorManager;

class PreparingResponseListener
{
    public function __construct(private SensorManager $sensor, private ExecutionState $state)
    {
        //
    }

    public function __invoke(PreparingResponse $event): void
    {
        try {
            if ($this->state->stage === ExecutionStage::Action) {
                $this->sensor->stage(ExecutionStage::Render);
            }
        } catch (Exception $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
