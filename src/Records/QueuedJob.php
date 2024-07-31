<?php

namespace Laravel\Nightwatch\Records;

use Laravel\Nightwatch\Types\Str;

/**
 * @internal
 */
final class QueuedJob
{
    public int $v = 1;

    public function __construct(
        public int $timestamp,
        public string $deploy,
        public string $server,
        public string $group,
        public string $trace_id,
        public string $execution_context,
        public string $execution_id,
        public int $execution_offset,
        public string $user,
        // --- /
        public string $job_id,
        public string $name,
        public string $connection,
        public string $queue,
    ) {
        $this->name = Str::tinyText($this->name);
        $this->connection = Str::tinyText($this->connection);
        $this->queue = Str::tinyText($this->queue);
    }
}
