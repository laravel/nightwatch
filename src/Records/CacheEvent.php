<?php

namespace Laravel\Nightwatch\Records;

use Laravel\Nightwatch\Types\TinyText;

final class CacheEvent
{
    public int $v = 1;

    /**
     * @param  non-empty-string  $timestamp
     * @param  non-empty-string  $group
     * @param  non-empty-string  $trace_id
     * @param  'job'|'request'  $execution_context
     * @param  non-empty-string  $execution_id
     * @param  non-empty-string  $store
     * @param  non-empty-string  $key
     * @param  'hit'|'miss'  $type
     */
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
        public string $store,
        public string $key,
        public string $type,
    ) {
        $this->store = TinyText::limit($this->store);
        $this->key = TinyText::limit($this->key);
    }
}
