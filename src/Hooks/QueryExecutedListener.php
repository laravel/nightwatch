<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Database\Events\QueryExecuted;
use Laravel\Nightwatch\SensorManager;
use Throwable;

use function debug_backtrace;

final class QueryExecutedListener
{
    public function __construct(private SensorManager $sensor)
    {
        //
    }

    public function __invoke(QueryExecuted $event): void
    {
        try {
            // We have temporarily disabled debug_backtrace to reduce the memory impact
            // $this->sensor->query($event, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, limit: 20));
            $this->sensor->query($event, []);
        } catch (Throwable $e) {
            $this->sensor->exception($e);
        }
    }
}
