<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\SensorManager;
use Throwable;

final class CommandListener
{
    public function __construct(private SensorManager $sensor)
    {
        //
    }

    public function __invoke($event): void
    {
        try {
            // $this->sensor->stage('action');
            $this->sensor->command($event);
        } catch (Throwable $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
