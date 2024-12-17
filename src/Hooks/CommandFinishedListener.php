<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\SensorManager;
use Laravel\Nightwatch\State\CommandState;
use Throwable;

final class CommandFinishedListener
{
    public function __construct(
        private SensorManager $sensor,
        private CommandState $commandState,
    ) {
        //
    }

    public function __invoke(CommandFinished $event): void
    {
        try {
            if ($event->command === $this->commandState->name && ! $this->commandState->terminatingEventExists) {
                $this->sensor->stage(ExecutionStage::Terminating);
            }
        } catch (Throwable $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
