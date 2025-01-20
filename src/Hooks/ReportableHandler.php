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
            if ($this->nightwatch->state->executionSource === 'job') {
                return;
            }
        } catch (Throwable $exception) { // @phpstan-ignore catch.neverThrown
            $this->nightwatch->report($exception);
        }

        $this->nightwatch->report($e);
    }
}
