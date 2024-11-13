<?php

namespace Laravel\Nightwatch\Hooks;

use Exception;
use Laravel\Nightwatch\SensorManager;
use Throwable;

class ReportableHandler
{
    public function __construct(private SensorManager $sensor)
    {
        //
    }

    public function __invoke(Throwable $exception)
    {
        try {
            $this->sensor->exception($exception);
        } catch (Exception $e) {
            //
        }
    }
}
