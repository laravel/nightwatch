<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Laravel\Nightwatch\SensorManager;

final class ExceptionHandlerResolvedHandler
{
    public function __construct(private SensorManager $sensor)
    {
        //
    }

    public function __invoke(ExceptionHandler $handler): void
    {
        if ($handler instanceof Handler) {
            // TODO ensure this isn't a memory leak in Octane
            $handler->reportable(new ReportableHandler($this->sensor));
        }
    }
}
