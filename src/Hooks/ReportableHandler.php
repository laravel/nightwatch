<?php

namespace Laravel\Nightwatch\Hooks;

use Exception;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\SensorManager;
use Throwable;

final class ReportableHandler
{
    public function __construct(private SensorManager $sensor)
    {
        //
    }

    public function __invoke(Throwable $exception): void
    {
        try {
            $this->sensor->exception($exception);
        } catch (Exception $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
