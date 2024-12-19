<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Contracts\Foundation\Application;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\SensorManager;
use Throwable;

final class CommandBootedHandler
{
    public function __construct(private SensorManager $sensor)
    {
        //
    }

    public function __invoke(Application $app): void
    {
        try {
            $this->sensor->stage(ExecutionStage::Action);
        } catch (Throwable $e) {
            $this->sensor->exception($e);
        }
    }
}
