<?php

namespace Laravel\Nightwatch\Records;

final class CacheEvent
{
    /**
     * @param  'hit'|'miss'|'write'|'write-failure'|'delete'|'delete-failure'  $type
     */
    public function __construct(
        public readonly string $store,
        public string $key,
        public readonly string $type,
        public readonly int $duration,
        public readonly int $ttl,
    ) {
        //
    }
}
