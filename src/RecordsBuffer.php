<?php

namespace Laravel\Nightwatch;

use Countable;
use Laravel\Nightwatch\Records\Record;

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

    public function pull(): Payload
    {
        if ($this->records === []) {
            return Payload::json('[]');
        }

        $records = json_encode($this->records, flags: JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);

        $this->records = [];

        return Payload::json($records);
    }

    public function flush(): void
    {
        $this->records = [];
    }
}
