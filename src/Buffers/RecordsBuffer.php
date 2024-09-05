<?php

namespace Laravel\Nightwatch\Buffers;

use Laravel\Nightwatch\Records\CacheEvent;
use Laravel\Nightwatch\Records\Command;
use Laravel\Nightwatch\Records\Exception;
use Laravel\Nightwatch\Records\OutgoingRequest;
use Laravel\Nightwatch\Records\Query;
use Laravel\Nightwatch\Records\QueuedJob;
use Laravel\Nightwatch\Records\Request;

use function json_encode;

/**
 * @internal
 */
final class RecordsBuffer
{
    /**
     * @var array{
     *            requests: list<Request>,
     *            cache_events: list<CacheEvent>,
     *            commands: list<Command>,
     *            exceptions: list<Exception>,
     *            outgoing_requests: list<OutgoingRequest>,
     *            queries: list<Query>,
     *            queued_jobs: list<QueuedJob>,
     *            }
     */
    private array $records = [
        'requests' => [],
        'cache_events' => [],
        'commands' => [],
        'exceptions' => [],
        'job_attempts' => [],
        'lazy_loads' => [],
        'logs' => [],
        'mail' => [],
        'notifications' => [],
        'outgoing_requests' => [],
        'queries' => [],
        'queued_jobs' => [],
    ];

    private int $recordsCount = 0;

    public function writeRequest(Request $request): void
    {
        $this->records['requests'][] = $request;

        $this->recordsCount++;
    }

    public function writeCommand(Command $command): void
    {
        $this->records['commands'][] = $command;

        $this->recordsCount++;
    }

    public function writeQuery(Query $query): void
    {
        $this->records['queries'][] = $query;

        $this->recordsCount++;
    }

    public function writeException(Exception $exception): void
    {
        $this->records['exceptions'][] = $exception;

        $this->recordsCount++;
    }

    public function writeCacheEvent(CacheEvent $cacheEvent): void
    {
        $this->records['cache_events'][] = $cacheEvent;

        $this->recordsCount++;
    }

    public function writeOutgoingRequest(OutgoingRequest $outgoingRequest): void
    {
        $this->records['outgoing_requests'][] = $outgoingRequest;

        $this->recordsCount++;
    }

    public function writeQueuedJob(QueuedJob $queuedJob): void
    {
        $this->records['queued_jobs'][] = $queuedJob;

        $this->recordsCount++;
    }

    /**
     * TODO should we only send keys that we have records for? It would reduce
     * the memory footprint in the agent but introduce a slight overhead in the
     * lambda.
     * TODO error handling. We should also do a general sweep
     */
    public function flush(): string
    {
        if ($this->recordsCount === 0) {
            return '';
        }

        $records = json_encode($this->records, flags: JSON_THROW_ON_ERROR);

        $this->records = [
            'requests' => [],
            'cache_events' => [],
            'commands' => [],
            'exceptions' => [],
            'job_attempts' => [],
            'lazy_loads' => [],
            'logs' => [],
            'mail' => [],
            'notifications' => [],
            'outgoing_requests' => [],
            'queries' => [],
            'queued_jobs' => [],
        ];

        $this->recordsCount = 0;

        return $records;
    }
}
