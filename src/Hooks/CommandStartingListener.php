<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Console\Events\CommandStarting;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\CommandState;
use Throwable;

final class CommandStartingListener
{
    /**
     * @param  Core<CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(CommandStarting $event): void
    {
        try {
            if ($this->nightwatch->state->name === null) {
                $this->nightwatch->state->name = $event->command;
            }
        } catch (Throwable $e) { // @phpstan-ignore catch.neverThrown
            $this->nightwatch->report($e);
        }
    }
}
