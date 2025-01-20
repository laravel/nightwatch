<?php

namespace Laravel\Nightwatch\Records;

use Laravel\Nightwatch\LazyValue;
use Laravel\Nightwatch\Types\Str;

/**
 * @internal
 */
final class JobAttempt
{
    public int $v = 1;

    public string $t = 'job-attempt';

    /**
     * @param  string|LazyValue<string>  $user
     * @param  'processed'|'released'|'failed'  $status
     */
    public function __construct(
        public float $timestamp,
        public string $deploy,
        public string $server,
        public string $_group,
        public string $trace_source,
        public string $trace_id,
        public string|LazyValue $user,
        // --- /
        public string $job_id,
        public string $attempt_id,
        public int $attempt,
        public string $name,
        public string $connection,
        public string $queue,
        public string $status,
        public int $duration,
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
        $this->connection = Str::tinyText($this->connection);
        $this->queue = Str::tinyText($this->queue);
    }
}
