<?php

namespace Laravel\Nightwatch\Hooks;

use Exception;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\SensorManager;

final class RequestHandledListener
{
    public function __construct(private SensorManager $sensor)
    {
        //
    }

    public function __invoke(RequestHandled $event): void
    {
        try {
            $this->sensor->stage(ExecutionStage::Sending);
        } catch (Exception $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
