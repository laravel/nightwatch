<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Foundation\Events\Terminating;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Throwable;

final class TerminatingListener
{
    /**
     * @param  Core<RequestState|CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(Terminating $event): void
    {
        try {
            $this->nightwatch->sensor->stage(ExecutionStage::Terminating);
        } catch (Throwable $e) {
            $this->nightwatch->report($e);
        }
    }
}
