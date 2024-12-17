<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\State\CommandState;
use Throwable;

final class CommandStartingListener
{
    public function __construct(private CommandState $commandState)
    {
        //
    }

    public function __invoke(CommandStarting $event): void
    {
        try {
            if ($this->commandState->name === null) {
                $this->commandState->name = $event->command;
            }
        } catch (Throwable $e) { // @phpstan-ignore catch.neverThrown
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
