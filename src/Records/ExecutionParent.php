<?php

namespace Laravel\Nightwatch\Records;

final class ExecutionParent
{
    /**
     * @param  non-negative-int  $queries
     * @param  non-negative-int  $queries_duration
     * @param  non-negative-int  $lazy_loads
     * @param  non-negative-int  $lazy_loads_duration
     * @param  non-negative-int  $jobs_queued
     * @param  non-negative-int  $mail_queued
     * @param  non-negative-int  $mail_sent
     * @param  non-negative-int  $mail_duration
     * @param  non-negative-int  $notifications_queued
     * @param  non-negative-int  $notifications_sent
     * @param  non-negative-int  $notifications_duration
     * @param  non-negative-int  $outgoing_requests
     * @param  non-negative-int  $outgoing_requests_duration
     * @param  non-negative-int  $files_read
     * @param  non-negative-int  $files_read_duration
     * @param  non-negative-int  $files_written
     * @param  non-negative-int  $files_written_duration
     * @param  non-negative-int  $cache_hits
     * @param  non-negative-int  $cache_misses
     * @param  non-negative-int  $hydrated_models
     * @param  non-negative-int  $peak_memory_usage_kilobytes
     */
    public function __construct(
        public int $queries = 0,
        public int $queries_duration = 0,
        public int $lazy_loads = 0,
        public int $lazy_loads_duration = 0,
        public int $jobs_queued = 0,
        public int $mail_queued = 0,
        public int $mail_sent = 0,
        public int $mail_duration = 0,
        public int $notifications_queued = 0,
        public int $notifications_sent = 0,
        public int $notifications_duration = 0,
        public int $outgoing_requests = 0,
        public int $outgoing_requests_duration = 0,
        public int $files_read = 0,
        public int $files_read_duration = 0,
        public int $files_written = 0,
        public int $files_written_duration = 0,
        public int $cache_hits = 0,
        public int $cache_misses = 0,
        public int $hydrated_models = 0,
        public int $peak_memory_usage_kilobytes = 0,
    ) {
        //
    }
}
