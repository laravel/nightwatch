<?php

namespace Laravel\Nightwatch\Sensors;

use Carbon\CarbonImmutable;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Support\Facades\Auth;
use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\Records\CacheEvent;
use Laravel\Nightwatch\Records\ExecutionParent;

final class CacheEventSensor
{
    public function __construct(
        private RecordsBuffer $recordsBuffer,
        private ExecutionParent $executionParent,
        private string $deployId,
        private string $server,
        private string $traceId,
    ) {
        //
    }

    public function __invoke(CacheMissed|CacheHit $event)
    {
        // TODO capture the microtime before hitting the cache on the event.
        $now = CarbonImmutable::now();

        if ($event::class === CacheHit::class) {
            $type = 'hit';
            $this->executionParent->cache_hits++;
        } else {
            $type = 'miss';
            $this->executionParent->cache_misses++;
        }

        $this->recordsBuffer->writeCacheEvent(new CacheEvent(
            timestamp: $now->toDateTimeString(),
            deploy_id: $this->deployId,
            server: $this->server,
            group: hash('sha256', ''), // TODO
            trace_id: $this->traceId,
            execution_context: 'request', // TODO
            execution_id: '00000000-0000-0000-0000-000000000000', // TODO
            user: Auth::id() ?? '', // TODO allow this to be customised
            store: $event->storeName, // this can be nullable? fallback to default?
            key: $event->key,
            type: $type,
        ));
    }
}
