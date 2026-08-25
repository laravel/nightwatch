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

    public function eventCount(): int
    {
        return array_reduce($this->records, fn ($count, $record) => match ($record['t']) {
            'user' => $count,
            default => $count + 1,
        }, 0);
    }
}
