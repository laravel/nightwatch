<?php

namespace Laravel\Nightwatch\Records;

use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Types\Str;

/**
 * @internal
 */
final class ExecutionState
{
    public int $v = 1;

    /**
     * @param  array<value-of<ExecutionStage>, int>  $stageDurations
     */
    public function __construct(
        public string $trace,
        public string $id,
        public string $context,
        public string $deploy,
        public string $server,
        public float $currentExecutionStageStartedAtMicrotime,
        public ExecutionStage $stage = ExecutionStage::Bootstrap,
        public array $stageDurations = [
            ExecutionStage::Bootstrap->value => 0,
            ExecutionStage::BeforeMiddleware->value => 0,
            ExecutionStage::Action->value => 0,
            ExecutionStage::Render->value => 0,
            ExecutionStage::AfterMiddleware->value => 0,
            ExecutionStage::Sending->value => 0,
            ExecutionStage::Terminating->value => 0,
            ExecutionStage::End->value => 0,
        ],
        public int $exceptions = 0,
        public int $queries = 0,
        public int $lazy_loads = 0,
        public int $jobs_queued = 0,
        public int $mail_queued = 0,
        public int $mail_sent = 0,
        public int $notifications_queued = 0,
        public int $notifications_sent = 0,
        public int $outgoing_requests = 0,
        public int $files_read = 0,
        public int $files_written = 0,
        public int $cache_hits = 0,
        public int $cache_misses = 0,
        public int $cache_writes = 0,
        public int $hydrated_models = 0,
        public int $peak_memory_usage = 0,
    ) {
        $this->deploy = Str::tinyText($this->deploy);
        $this->server = Str::tinyText($this->server);
    }
}
