<?php

namespace Laravel\Nightwatch\Records;

use Laravel\Nightwatch\LazyValue;
use Laravel\Nightwatch\Types\Str;

/**
 * @internal
 */
final class ScheduledTask
{
    public int $v = 1;

    public string $t = 'scheduled-task';

    /**
     * @param  string|LazyValue<string>  $trace_id
     * @param  'processed'|'skipped'|'failed'  $status
     */
    public function __construct(
        public float $timestamp,
        public string $deploy,
        public string $server,
        public string $_group,
        public string|LazyValue $trace_id,
        // --- //
        public string $name,
        public string $cron,
        public string $timezone,
        public bool $without_overlapping,
        public bool $on_one_server,
        public bool $run_in_background,
        public bool $even_in_maintenance_mode,
        public string $status,
        public int $duration,
        // --- //
        public int $exceptions,
        public int $logs,
        public int $queries,
        public int $lazy_loads,
        public int $jobs_queued,
        public int $mail,
        public int $notifications,
        public int $outgoing_requests,
        public int $files_read,
        public int $files_written,
        public int $cache_events,
        public int $hydrated_models,
        public int $peak_memory_usage,
    ) {
        $this->name = Str::tinyText($this->name);
    }
}
