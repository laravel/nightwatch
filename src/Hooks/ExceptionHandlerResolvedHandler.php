<?php

namespace Laravel\Nightwatch\Hooks;

use Exception;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Laravel\Nightwatch\SensorManager;
use Throwable;

class ExceptionHandlerResolvedHandler
{
    public function __construct(private SensorManager $sensor)
    {
        //
    }

    public function __invoke(ExceptionHandler $handler): void
    {
        if (! $handler instanceof Handler) {
            return;
        }

        $handler->reportable(function (Throwable $exception) {
            try {
                $this->sensor->exception($exception);
            } catch (Exception $e) {
                //
            }
        });
    }
}

