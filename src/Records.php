<?php

namespace Laravel\Nightwatch;

use Laravel\Nightwatch\Records\CacheEvent;
use Laravel\Nightwatch\Records\Exception;
use Laravel\Nightwatch\Records\OutgoingRequest;
use Laravel\Nightwatch\Records\Query;
use Laravel\Nightwatch\Records\Request;

final class Records
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

    public function addRequest(Request $request): void
    {
        // todo conver to properties
        $this->records['requests'][] = $request;
    }

    public function addQuery(Query $query): void
    {
        $this->records['queries'][] = $query;
    }

    public function addException(Exception $exception): void
    {
        $this->records['exceptions'][] = $exception;
    }

    public function addCacheEvent(CacheEvent $cacheEvent): void
    {
        $this->records['cache_events'][] = $cacheEvent;
    }

    public function addOutgoingRequest(OutgoingRequest $outgoingRequest): void
    {
        $this->records['outgoing_requests'][] = $outgoingRequest;
    }

    public function flush(): string
    {
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

        return $records;
    }
}
