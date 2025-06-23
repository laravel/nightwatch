<?php

namespace Laravel\Nightwatch\Events;

final class Query
{
    public function __construct(
        public string $sql,
        public readonly string $connection,
    ) {
        //
    }
}
