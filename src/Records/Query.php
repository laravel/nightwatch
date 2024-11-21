<?php

namespace Laravel\Nightwatch\Records;

use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Types\Str;

/**
 * @internal
 */
final class Query
{
    public int $v = 1;

    public string $t = 'query';

    public function __construct(
        public float $timestamp,
        public string $deploy,
        public string $server,
        public string $_group,
        public string $trace_id,
        public string $execution_context,
        public string $execution_id,
        public ExecutionStage $execution_stage,
        public string $user,
        // --- //
        public string $sql,
        public string $file,
        public int $line,
        public int $duration,
        public string $connection,
    ) {
        $this->sql = Str::mediumText($this->sql);
        $this->file = Str::tinyText($this->file);
        $this->connection = Str::tinyText($this->connection);
    }
}
