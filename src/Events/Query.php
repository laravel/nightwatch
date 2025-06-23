<?php

namespace Laravel\Nightwatch\Events;

class Query
{
    public function __construct(
        public string $sql,
        public string $connection,
    ) {
        //
    }
}

