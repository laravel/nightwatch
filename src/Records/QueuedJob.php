<?php

namespace Laravel\Nightwatch\Records;

use Laravel\Nightwatch\Types\TinyText;

final class QueuedJob
{
    // public int $v = 1;

    public function __construct(
        public string $timestamp,
        public string $deploy_id,
        public string $server,
        public string $group,
        public string $trace_id,
        public string $execution_context,
        public string $execution_id,
        public string $user,
        // --- /
        public string $job_id,
        public string $name,
        public string $connection,
        public string $queue,
    ) {
        $this->name = TinyText::limit($this->name);
        $this->connection = TinyText::limit($this->connection);
        $this->queue = TinyText::limit($this->queue);
    }
}
