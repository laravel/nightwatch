<?php

namespace Laravel\Nightwatch\Records;

use Laravel\Nightwatch\Types\TinyText;

final class CacheEvent
{
    public int $v = 1;

    public function __construct(
        public int $timestamp,
        public string $deploy_id,
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
    ) {
        $this->store = TinyText::limit($this->store);
        $this->key = TinyText::limit($this->key);
    }
}
