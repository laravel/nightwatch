<?php

namespace Laravel\Nightwatch\Records;

use Laravel\Nightwatch\Types\Str;

/**
 * @internal
 */
final class CacheEvent
{
    public int $v = 1;

    /**
     * @param  'hit'|'miss'|'write'  $type
     */
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
        // --- //
        public string $store,
        public string $key,
        public string $type,
        public int $duration,
        public int $ttl = 0,
    ) {
        $this->store = Str::tinyText($this->store);
        $this->key = Str::tinyText($this->key);
    }
}
