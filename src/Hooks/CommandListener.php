<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\SensorManager;
use Throwable;

final class CommandListener
{
    public function __construct(private SensorManager $sensor)
    {
        //
    }

    public function __invoke(CommandStarting|CommandFinished $event): void
    {
        try {
            $this->sensor->command($event);
        } catch (Throwable $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
