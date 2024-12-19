<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Database\Events\QueryExecuted;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Throwable;

use function debug_backtrace;

final class QueryExecutedListener
{
    /**
     * @param  Core<RequestState>|Core<CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(QueryExecuted $event): void
    {
        try {
            // We have temporarily disabled debug_backtrace to reduce the memory impact
            // $this->nightwatch->sensor->query($event, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, limit: 20));
            $this->nightwatch->sensor->query($event, []);
        } catch (Throwable $e) {
            $this->nightwatch->report($e);
        }
    }
}
