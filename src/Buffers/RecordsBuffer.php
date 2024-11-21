<?php

namespace Laravel\Nightwatch\Buffers;

use Laravel\Nightwatch\Records\CacheEvent;
use Laravel\Nightwatch\Records\Command;
use Laravel\Nightwatch\Records\Exception;
use Laravel\Nightwatch\Records\OutgoingRequest;
use Laravel\Nightwatch\Records\Query;
use Laravel\Nightwatch\Records\QueuedJob;
use Laravel\Nightwatch\Records\Request;

use function count;
use function json_encode;

/**
 * @internal
 */
final class RecordsBuffer
{
    /**
     * @var list<Request|Command|Exception|CacheEvent|OutgoingRequest|Query|QueuedJob>
     */
    private array $records = [];

    public function write(Request|Command|Exception|CacheEvent|OutgoingRequest|Query|QueuedJob $record): void
    {
        $this->records[] = $record;
    }

    public function flush(): string
    {
        if (count($this->records) === 0) {
            return '';
        }

        $records = json_encode($this->records, flags: JSON_THROW_ON_ERROR);

        $this->records = [];

        return $records;
    }
}
