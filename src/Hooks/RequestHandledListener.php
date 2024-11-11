<?php

namespace Laravel\Nightwatch\Hooks;

use Exception;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\SensorManager;

class RequestHandledListener
{
    public function __construct(private SensorManager $sensor)
    {
        //
    }

    public function __invoke(RequestHandled $event): void
    {
        try {
            $this->sensor->stage(ExecutionStage::Sending);
        } catch (Exception $e) {
            //
        }
    }
}
