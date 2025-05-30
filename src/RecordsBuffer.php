<?php

namespace Laravel\Nightwatch;

use Countable;
use Laravel\Nightwatch\Records\Record;

use function array_shift;
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

        if ($filters === []) {
            $records = $this->records;
            $this->records = [];
        } else {
            $records = [];

            while ($record = array_shift($this->records)) {
                foreach ($filters as $filter) {
                    if ($filter($record)) {
                        $records[] = $record;
                    }
                }
            }
        }

        $records = json_encode($records, flags: JSON_THROW_ON_ERROR);

        return Payload::json($records);
    }

    public function flush(): void
    {
        $this->records = [];
    }
}
