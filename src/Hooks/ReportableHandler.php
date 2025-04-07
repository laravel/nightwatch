<?php

namespace Laravel\Nightwatch\Hooks;

use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Throwable;

use function in_array;

/**
 * @internal
 */
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
        if (in_array($this->nightwatch->state->source, ['job', 'schedule'], true)) {
            return;
        }

        $this->nightwatch->report($e);
    }
}
