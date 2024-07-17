<?php

namespace Laravel\Nightwatch\Records;

/**
 * @internal
 */
final class ExecutionParent
{
    public int $v = 1;

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
