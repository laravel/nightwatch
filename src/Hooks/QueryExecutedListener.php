<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Database\Events\QueryExecuted;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Throwable;

use function debug_backtrace;

/**
 * @internal
 */
final class QueryExecutedListener
{
    /**
     * @param  Core<RequestState|CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(QueryExecuted $event): void
    {
        try {
            $this->nightwatch->sensor->query($event, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, limit: 20));
        } catch (Throwable $e) {
            $this->nightwatch->report($e);
        }
    }
}
