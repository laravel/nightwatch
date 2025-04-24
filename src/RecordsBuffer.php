<?php

namespace Laravel\Nightwatch;

use Countable;
use Laravel\Nightwatch\Records\CacheEvent;
use Laravel\Nightwatch\Records\Command;
use Laravel\Nightwatch\Records\Exception;
use Laravel\Nightwatch\Records\JobAttempt;
use Laravel\Nightwatch\Records\Log;
use Laravel\Nightwatch\Records\Mail;
use Laravel\Nightwatch\Records\Notification;
use Laravel\Nightwatch\Records\OutgoingRequest;
use Laravel\Nightwatch\Records\Query;
use Laravel\Nightwatch\Records\QueuedJob;
use Laravel\Nightwatch\Records\Request;
use Laravel\Nightwatch\Records\ScheduledTask;
use Laravel\Nightwatch\Records\User;

use function array_shift;
use function count;
use function json_encode;

/**
 * @internal
 */
class RecordsBuffer implements Countable
{
    /**
     * @var list<Request|Command|Exception|CacheEvent|OutgoingRequest|Query|QueuedJob|JobAttempt|Mail|Notification|Log|User|ScheduledTask>
     */
    private array $records = [];

    public function write(Request|Command|Exception|CacheEvent|OutgoingRequest|Query|QueuedJob|JobAttempt|Mail|Notification|Log|User|ScheduledTask $record): void
    {
        // TODO temporary limit
        if (count($this->records) > 499) {
            array_shift($this->records);
        }

        $this->records[] = $record;
    }

    public function count(): int
    {
        return count($this->records);
    }

    public function pull(): string
    {
        if ($this->records === []) {
            return '[]';
        }

        $records = json_encode($this->records, flags: JSON_THROW_ON_ERROR);

        $this->records = [];

        return $records;
    }

    public function flush(): void
    {
        $this->records = [];
    }
}
