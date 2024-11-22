<?php

namespace Laravel\Nightwatch\Records;

use Laravel\Nightwatch\Types\Str;

/**
 * @internal
 */
final class Command
{
    public int $v = 1;

    public string $t = 'command';

    /**
     * TODO limit size of all int values across all record types.
     */
    public function __construct(
        public int $timestamp,
        public string $deploy,
        public string $server,
        public string $group,
        public string $trace_id,
        public string $user,
        // --- //
        public string $name,
        public string $command,
        public int $exit_code,
        public int $duration,
        // --- //
        public int $queries,
        public int $queries_duration,
        public int $lazy_loads,
        public int $lazy_loads_duration,
        public int $jobs_queued,
        public int $mail_queued,
        public int $mail_sent,
        public int $mail_duration,
        public int $notifications_queued,
        public int $notifications_sent,
        public int $notifications_duration,
        public int $outgoing_requests,
        public int $outgoing_requests_duration,
        public int $files_read,
        public int $files_read_duration,
        public int $files_written,
        public int $files_written_duration,
        public int $cache_hits,
        public int $cache_misses,
        public int $cache_writes,
        public int $cache_forgets,
        public int $hydrated_models,
        public int $peak_memory_usage,
    ) {
        $this->name = Str::tinyText($this->name);
        $this->command = Str::text($this->command);
    }
}
