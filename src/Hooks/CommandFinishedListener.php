<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Console\Events\CommandFinished;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\State\CommandState;
use Throwable;

final class CommandFinishedListener
{
    /**
     * @param  Core<CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(CommandFinished $event): void
    {
        try {
            if ($event->command === $this->nightwatch->state->name && ! $this->nightwatch->state->terminatingEventExists) {
                $this->nightwatch->sensor->stage(ExecutionStage::Terminating);
            }
        } catch (Throwable $e) {
            $this->nightwatch->report($e);
        }
    }
}
