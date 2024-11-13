<?php

namespace Laravel\Nightwatch\Hooks;

use Exception;
use Illuminate\Contracts\Foundation\Application;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\SensorManager;

class BootedHandler
{
    public function __construct(private SensorManager $sensor)
    {
        //
    }

    public function __invoke(Application $app): void
    {
        try {
            $this->sensor->stage(ExecutionStage::BeforeMiddleware);
        } catch (Exception $e) {
            //
        }
    }
}
