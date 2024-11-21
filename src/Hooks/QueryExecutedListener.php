<?php

namespace Laravel\Nightwatch\Hooks;

use Exception;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\SensorManager;

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
            $this->sensor->query($event, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));
        } catch (Exception $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
