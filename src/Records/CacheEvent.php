<?php

namespace Laravel\Nightwatch\Records;

use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Types\Str;

/**
 * @internal
 */
final class CacheEvent
{
    public int $v = 1;

    public string $t = 'cache-event';

    /**
     * @param  'hit'|'miss'|'write'|'write-failure'|'forget'|'forget-failure'  $type
     */
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
        public string $store,
        public string $key,
        public string $type,
        public int $duration,
        public int $ttl,
    ) {
        $this->store = Str::tinyText($this->store);
        $this->key = Str::tinyText($this->key);
    }
}
