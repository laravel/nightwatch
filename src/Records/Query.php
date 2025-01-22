<?php

namespace Laravel\Nightwatch\Records;

use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\LazyValue;
use Laravel\Nightwatch\Types\Str;

/**
 * @internal
 */
final class Query
{
    public int $v = 1;

    public string $t = 'query';

    /**
     * @param  string|LazyValue<string>  $trace_id
     * @param  string|LazyValue<string>  $execution_id
     * @param  string|LazyValue<string>  $user
     */
    public function __construct(
        public float $timestamp,
        public string $deploy,
        public string $server,
        public string $_group,
        public string|LazyValue $trace_id,
        public string $execution_source,
        public string|LazyValue $execution_id,
        public ExecutionStage $execution_stage,
        public string|LazyValue $user,
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
