<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Contracts\Foundation\Application;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\State\CommandState;
use Throwable;

final class CommandBootedHandler
{
    /**
     * @param  Core<CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(Application $app): void
    {
        try {
            $this->nightwatch->sensor->stage(ExecutionStage::Action);
        } catch (Throwable $e) {
            $this->nightwatch->report($e);
        }
    }
}
