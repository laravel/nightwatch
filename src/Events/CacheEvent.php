<?php

namespace Laravel\Nightwatch\Events;

final class CacheEvent
{
    /**
     * @param  'hit'|'miss'|'write'|'write-failure'|'delete'|'delete-failure'  $type
     */
    public function __construct(
        public string $key,
        public readonly string $store,
        public readonly string $type,
    ) {
        //
    }
}
