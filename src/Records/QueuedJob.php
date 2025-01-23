<?php

namespace Laravel\Nightwatch\Records;

use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\LazyValue;
use Laravel\Nightwatch\Types\Str;

/**
 * @internal
 */
final class QueuedJob
{
    public int $v = 1;

    public string $t = 'queued-job';

    /**
     * @param  string|LazyValue<string>  $trace_id
     * @param  LazyValue<string>  $execution_id
     * @param  string|LazyValue<string>  $user
     */
    public function __construct(
        public float $timestamp,
        public string $deploy,
        public string $server,
        public string $_group,
        public string|LazyValue $trace_id,
        public string $execution_source,
        public LazyValue $execution_id,
        public ExecutionStage $execution_stage,
        public string|LazyValue $user,
        // --- /
        public string $job_id,
        public string $name,
        public string $connection,
        public string $queue,
        public int $duration,
    ) {
        $this->name = Str::tinyText($this->name);
        $this->connection = Str::tinyText($this->connection);
        $this->queue = Str::tinyText($this->queue);
    }
}
