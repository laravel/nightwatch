<?php

namespace Laravel\Nightwatch\Hooks;

use Exception;
use Illuminate\Contracts\Container\Container;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\SensorManager;

class BootedHandler
{
    public function __construct(private SensorManager $sensor)
    {
        //
    }

    public function __invoke(Container $app): void
    {
        try {
            $this->sensor->stage(ExecutionStage::BeforeMiddleware);
        } catch (Exception $e) {
            //
        }
    }
}
