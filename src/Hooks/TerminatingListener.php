<?php

namespace Laravel\Nightwatch\Hooks;

use Exception;
use Illuminate\Foundation\Events\Terminating;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\SensorManager;

class TerminatingListener
{
    public function __construct(private SensorManager $sensor)
    {
        //
    }

    public function __invoke(Terminating $event): void
    {
        try {
            $this->sensor->stage(ExecutionStage::Terminating);
        } catch (Exception $e) {
            //
        }
    }
}
