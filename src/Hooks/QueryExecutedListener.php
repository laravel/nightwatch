<?php

namespace Laravel\Nightwatch\Hooks;

use Exception;
use Illuminate\Database\Events\QueryExecuted;
use Laravel\Nightwatch\SensorManager;

use function debug_backtrace;

class QueryExecutedListener
{
    public function __construct(private SensorManager $sensor)
    {
        //
    }

    public function __invoke(QueryExecuted $event): void
    {
        try {
            $this->sensor->query($event, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));
        } catch (Exception $e) {
            //
        }
    }
}
