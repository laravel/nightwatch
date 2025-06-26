<?php

namespace Laravel\Nightwatch\Records;

final class Query
{
    public function __construct(
        public readonly string $sql,
        public readonly string $file,
        public readonly int $line,
        public readonly int $duration,
        public readonly string $connection,
    ) {
        //
    }
}
