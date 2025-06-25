<?php

namespace Laravel\Nightwatch\Records;

use Laravel\Nightwatch\LazyValue;
use Laravel\Nightwatch\Types\Str;

final class JobAttempt
{
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
        private readonly float $timestamp,
        private readonly string $deploy,
        private readonly string $server,
        private readonly string $_group,
        private readonly string|LazyValue $trace_id,
        private readonly string|LazyValue $user,
        // --- //
        private readonly string $jobId,
        private readonly string|LazyValue $attemptId,
        private readonly int $attempt,
        private readonly string $name,
        private readonly string $connection,
        private readonly string $queue,
        private readonly string $status,
        private readonly int $duration,
        // --- //
        private readonly LazyValue $exceptions,
        private readonly LazyValue $logs,
        private readonly LazyValue $queries,
        private readonly LazyValue $lazy_loads,
        private readonly LazyValue $jobs_queued,
        private readonly LazyValue $mail,
        private readonly LazyValue $notifications,
        private readonly LazyValue $outgoing_requests,
        private readonly LazyValue $files_read,
        private readonly LazyValue $files_written,
        private readonly LazyValue $cache_events,
        private readonly LazyValue $hydrated_models,
        private readonly LazyValue $peak_memory_usage,
        private readonly LazyValue $exception_preview,
    ) {
        //
    }

    /**
     * @internal
     */
    public function toBaseRecord(): Record
    {
        return new Record([
            'v' => 1,
            't' => 'job-attempt',
            'timestamp' => $this->timestamp,
            'deploy' => $this->deploy,
            'server' => $this->server,
            '_group' => $this->_group,
            'trace_id' => $this->trace_id,
            'user' => $this->user,
            // --- //
            'job_id' => $this->job_id,
            'attempt_id' => $this->attempt_id,
            'attempt' => $this->attempt,
            'name' => Str::text($this->name),
            'connection' => Str::tinyText($this->connection),
            'queue' => Str::tinyText($this->queue),
            'status' => $this->status,
            'duration' => $this->duration,
            // --- //
            'exceptions' => $this->exceptions,
            'logs' => $this->logs,
            'queries' => $this->queries,
            'lazy_loads' => $this->lazy_loads,
            'jobs_queued' => $this->jobs_queued,
            'mail' => $this->mail,
            'notifications' => $this->notifications,
            'outgoing_requests' => $this->outgoing_requests,
            'files_read' => $this->files_read,
            'files_written' => $this->files_written,
            'cache_events' => $this->cache_events,
            'hydrated_models' => $this->hydrated_models,
            'peak_memory_usage' => $this->peak_memory_usage,
            'exception_preview' => $this->exception_preview,
        ]);
    }
}
