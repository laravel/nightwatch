<?php

namespace Laravel\Nightwatch\Records;

use Laravel\Nightwatch\Types\MediumText;
use Laravel\Nightwatch\Types\TinyText;

final class Query
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
        // --- //
        public string $sql,
        public string $category,
        public string $file,
        public int $line,
        public int $duration,
        public string $connection,
    ) {
        $this->sql = MediumText::limit($this->sql);
        $this->file = TinyText::limit($this->file);
        $this->connection = TinyText::limit($this->connection);
    }
}
