<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\SensorManager;
use Throwable;

final class ExceptionHandlerResolvedHandler
{
    public function __construct(private SensorManager $sensor)
    {
        //
    }

    public function __invoke(ExceptionHandler $handler): void
    {
        try {
            if ($handler instanceof Handler) {
                // TODO ensure this isn't a memory leak in Octane
                $handler->reportable(new ReportableHandler($this->sensor));
            }
        } catch (Throwable $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
