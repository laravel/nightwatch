<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Foundation\Events\Terminating;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\SensorManager;
use Throwable;

final class TerminatingListener
{
    public function __construct(private SensorManager $sensor)
    {
        //
    }

    public function __invoke(Terminating $event): void
    {
        try {
            $this->sensor->stage(ExecutionStage::Terminating);
        } catch (Throwable $e) {
            $this->sensor->exception($e);
        }
    }
}
