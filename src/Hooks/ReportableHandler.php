<?php

namespace Laravel\Nightwatch\Hooks;

use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Throwable;

final class ReportableHandler
{
    /**
     * @param  Core<RequestState|CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(Throwable $e): void
    {
        try {
            if ($this->nightwatch->state->source === 'job') {
                return;
            }
        } catch (Throwable $exception) {
            $this->nightwatch->report($exception);

            return;
        }

        $this->nightwatch->report($e);
    }
}
