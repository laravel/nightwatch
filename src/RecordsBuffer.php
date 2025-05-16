<?php

namespace Laravel\Nightwatch;

use Countable;
use Laravel\Nightwatch\Records\Record;

use function array_filter;
use function array_values;
use function count;
use function json_encode;

/**
 * @internal
 */
class RecordsBuffer implements Countable
{
    /**
     * @var list<Record>
     */
    private array $records = [];

    public function write(Record $record): void
    {
        $this->records[] = $record;
    }

    public function count(): int
    {
        return count($this->records);
    }

    /**
     * @param  list<(callable(Record): bool)>  $filters
     */
    public function pull(array $filters = []): Payload
    {
        if ($this->records === []) {
            return Payload::json('[]');
        }

        $records = $this->records;
        $this->records = [];

        foreach ($filters as $filter) {
            $records = array_filter($records, $filter);
        }

        $records = array_values($records);

        $records = json_encode($records, flags: JSON_THROW_ON_ERROR);

        return Payload::json($records);
    }

    public function flush(): void
    {
        $this->records = [];
    }
}
