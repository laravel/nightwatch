<?php

namespace Laravel\Nightwatch\Hooks;

use Laravel\Nightwatch\SensorManager;
use Throwable;

final class ReportableHandler
{
    public function __construct(private SensorManager $sensor)
    {
        //
    }

    public function __invoke(Throwable $e): void
    {
        try {
            $this->sensor->exception($e);
        } catch (Throwable $e) {
            // Handle this!
        }
    }
}
