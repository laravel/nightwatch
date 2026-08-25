<?php

namespace Laravel\Nightwatch\Events;

final class IngestingEvents
{
    /**
     * @param  list<array<string, mixed>>  $records
     */
    public function __construct(
        public readonly array $records,
    ) {
        //
    }
}
