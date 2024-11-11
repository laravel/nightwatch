<?php

namespace Laravel\Nightwatch\Hooks;

use Exception;
use Illuminate\Routing\Events\ResponsePrepared;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Records\ExecutionState;
use Laravel\Nightwatch\SensorManager;

class ResponsePreparedListener
{
    public function __construct(private SensorManager $sensor, private ExecutionState $state)
    {
        //
    }

    public function __invoke(ResponsePrepared $event): void
    {
        try {
            if ($this->state->stage === ExecutionStage::Render) {
                $this->sensor->stage(ExecutionStage::AfterMiddleware);
            }
        } catch (Exception $e) {
            //
        }
    }
}
