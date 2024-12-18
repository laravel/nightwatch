<?php

namespace Laravel\Nightwatch;

use Throwable;

final class Core
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
