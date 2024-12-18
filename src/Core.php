<?php

namespace Laravel\Nightwatch;

use Throwable;

class Core
{
    public function __construct(private SensorManager $sensorManager)
    {
        //
    }

    public function report(Throwable $e): void
    {
        $this->sensorManager->exception($e);
    }
}
