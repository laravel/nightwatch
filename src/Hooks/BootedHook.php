<?php

namespace Laravel\Nightwatch\Hooks;

use Exception;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\SensorManager;

class BootedHook
{
    public function __construct(private SensorManager $sensor)
    {
        //
    }

    public function __invoke(): void
    {
        try {
            $this->sensor->stage(ExecutionStage::BeforeMiddleware);
        } catch (Exception $e) {
            //
        }
    }
}
