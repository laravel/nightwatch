<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\SensorManager;
use Throwable;

final class RequestBootedHandler
{
    public function __construct(private SensorManager $sensor)
    {
        //
    }

    public function __invoke(Application $app): void
    {
        try {
            $this->sensor->stage(ExecutionStage::BeforeMiddleware);
        } catch (Throwable $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
