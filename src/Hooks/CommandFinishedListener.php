<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\SensorManager;
use Laravel\Nightwatch\State\CommandState;
use Throwable;

use function class_exists;

final class CommandFinishedListener
{
    public function __construct(private SensorManager $sensor, private CommandState $commandState)
    {
        //
    }

    public function __invoke(CommandFinished $event): void
    {
        try {
            $this->commandState->name = $event->command;
        } catch (Throwable $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }

        if (! class_exists(Terminating::class)) {
            try {
                $this->sensor->stage(ExecutionStage::Terminating);
            } catch (Throwable $e) {
                Log::critical('[nightwatch] '.$e->getMessage());
            }
        }
    }
}
