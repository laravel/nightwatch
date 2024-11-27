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
        public float $timestamp,
        public string $deploy,
        public string $server,
        public string $_group,
        public string $trace_id,
        // --- //
        public string $class,
        public string $name,
        public string $command,
        public int $exit_code,
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
        $this->class = Str::text($this->class);
        $this->name = Str::tinyText($this->name);
        $this->command = Str::text($this->command);
    }
}
