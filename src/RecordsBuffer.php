<?php

namespace Laravel\Nightwatch;

use Laravel\Nightwatch\Records\CacheEvent;
use Laravel\Nightwatch\Records\Exception;
use Laravel\Nightwatch\Records\OutgoingRequest;
use Laravel\Nightwatch\Records\Query;
use Laravel\Nightwatch\Records\Request;

final class RecordsBuffer
{
    /**
     * @var array {
     *            requests: list<Request>,
     *            cache_events: list<CacheEvent>,
     *            commands: list<Command>,
     *            exceptions: list<Exception>,
     *            job_attempts: list<JobAttempt>,
     *            lazy_loads: list<LazyLoad>,
     *            logs: list<Log>,
     *            mail: list<Mail>,
     *            notifications: list<Notification>,
     *            outgoing_requests: list<OutgoingRequest>,
     *            queries: list<Query>,
     *            queued_jobs: list<QueuedJob>,
     *            }
     */
    private array $buffer = [
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

    /**
     * @var non-negative-int
     */
    private int $bufferCount = 0;

    public function writeRequest(Request $request): void
    {
        $this->buffer['requests'][] = $request;

        $this->bufferCount++;
    }

    public function writeQuery(Query $query): void
    {
        $this->buffer['queries'][] = $query;

        $this->bufferCount++;
    }

    public function writeException(Exception $exception): void
    {
        $this->buffer['exceptions'][] = $exception;

        $this->bufferCount++;
    }

    public function writeCacheEvent(CacheEvent $cacheEvent): void
    {
        $this->buffer['cache_events'][] = $cacheEvent;

        $this->bufferCount++;
    }

    public function writeOutgoingRequest(OutgoingRequest $outgoingRequest): void
    {
        $this->buffer['outgoing_requests'][] = $outgoingRequest;

        $this->bufferCount++;
    }

    public function flush(): string
    {
        $payload = json_encode($this->buffer, flags: JSON_THROW_ON_ERROR);

        $this->buffer = [
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

        $this->bufferCount = 0;

        return $payload;
    }
}
