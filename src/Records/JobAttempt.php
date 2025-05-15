<?php

namespace Laravel\Nightwatch\Records;

use Laravel\Nightwatch\LazyValue;
use Laravel\Nightwatch\Types\Str;

/**
 * @internal
 */
final class JobAttempt extends Record
{
    public int $v = 1;

    public string $t = 'job-attempt';

    /**
     * @param  string|LazyValue<string>  $trace_id
     * @param  string|LazyValue<string>  $user
     * @param  string|LazyValue<string>  $attempt_id
     * @param  'processed'|'released'|'failed'  $status
     * @param  LazyValue<int>  $exceptions
     * @param  LazyValue<int>  $logs
     * @param  LazyValue<int>  $queries
     * @param  LazyValue<int>  $lazy_loads
     * @param  LazyValue<int>  $jobs_queued
     * @param  LazyValue<int>  $mail
     * @param  LazyValue<int>  $notifications
     * @param  LazyValue<int>  $outgoing_requests
     * @param  LazyValue<int>  $files_read
     * @param  LazyValue<int>  $files_written
     * @param  LazyValue<int>  $cache_events
     * @param  LazyValue<int>  $hydrated_models
     * @param  LazyValue<int>  $peak_memory_usage
     * @param  LazyValue<string>  $exception_preview
     */
    public function __construct(
        public float $timestamp,
        public string $deploy,
        public string $server,
        public string $_group,
        public string|LazyValue $trace_id,
        public string|LazyValue $user,
        // --- /
        public string $job_id,
        public string|LazyValue $attempt_id,
        public int $attempt,
        public string $name,
        public string $connection,
        public string $queue,
        public string $status,
        public int $duration,
        public LazyValue $exceptions,
        public LazyValue $logs,
        public LazyValue $queries,
        public LazyValue $lazy_loads,
        public LazyValue $jobs_queued,
        public LazyValue $mail,
        public LazyValue $notifications,
        public LazyValue $outgoing_requests,
        public LazyValue $files_read,
        public LazyValue $files_written,
        public LazyValue $cache_events,
        public LazyValue $hydrated_models,
        public LazyValue $peak_memory_usage,
        public LazyValue $exception_preview,
    ) {
        $this->name = Str::text($this->name);
        $this->connection = Str::tinyText($this->connection);
        $this->queue = Str::tinyText($this->queue);
    }
}
