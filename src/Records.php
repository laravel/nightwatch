<?php

namespace Laravel\Nightwatch;

use Illuminate\Support\Collection;
use Laravel\Nightwatch\Records\CacheEvent;
use Laravel\Nightwatch\Records\Exception;
use Laravel\Nightwatch\Records\ExecutionParent;
use Laravel\Nightwatch\Records\OutgoingRequest;
use Laravel\Nightwatch\Records\Query;
use Laravel\Nightwatch\Records\Request;

// TODO: don't have this extend collection.
final class Records
{
    private array $records;

    public ExecutionParent $executionParent;

    public function __construct()
    {
        $this->flush();
    }

    public function addRequest(Request $request): void
    {
        // todo conver to properties
        $this->records['requests'][] = $request;
    }

    public function addQuery(Query $query): void
    {
        $this->records['queries'][] = $query;

        $this->executionParent->queries++;
        // TODO this is done to support the validation. Do we want to track
        // microseconds instead of milliseconds, though?
        $this->executionParent->queries_duration += $query->duration;
    }

    public function addException(Exception $exception): void
    {
        $this->records['exceptions'][] = $exception;

        // TODO: track the exception count?
    }

    public function addCacheEvent(CacheEvent $cacheEvent): void
    {
        $this->records['cache_events'][] = $cacheEvent;

        match ($cacheEvent->type) {
            'hit' => $this->executionParent->cache_hits++,
            'miss' => $this->executionParent->cache_misses++,
        };
    }

    public function addOutgoingRequest(OutgoingRequest $outgoingRequest): void
    {
        $this->records['outgoing_requests'][] = $outgoingRequest;

        $this->executionParent->outgoing_requests++;
        $this->executionParent->outgoing_requests_duration += $outgoingRequest->duration;
    }

    public function flush(): void
    {
        $this->executionParent = new ExecutionParent;

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
    }

    public function toJson(): string
    {
        return json_encode($this->records);
    }
}
