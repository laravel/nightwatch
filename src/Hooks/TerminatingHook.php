<?php

namespace Laravel\Nightwatch\Hooks;

use Exception;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\SensorManager;

class TerminatingHook
{
    public function __construct(private SensorManager $sensor)
    {
        //
    }

    public function __invoke(): void
    {
        try {
            $this->sensor->stage(ExecutionStage::Terminating);
        } catch (Exception $e) {
            //
        }
    }
}
