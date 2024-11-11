<?php

namespace Laravel\Nightwatch\Hooks;

use Exception;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Records\ExecutionState;
use Laravel\Nightwatch\SensorManager;

class PreparingResponseHook
{
    public function __construct(private SensorManager $sensor, private ExecutionState $state)
    {
        //
    }

    public function __invoke(): void
    {
        try {
            if ($this->state->stage === ExecutionStage::Action) {
                $this->sensor->stage(ExecutionStage::Render);
            }
        } catch (Exception $e) {
            //
        }
    }
}
