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

    /**
     * TODO capture the microtime before hitting the cache on the event instead
     * of the current time. Requires a framework modification. Would be cool to
     * capture the duration as well.
     * TODO allow auth to be customised?
     * TODO inject auth manager into the class.
     * TODO `$event->storeName` can be nullable. We should likely fallback to
     * the default value.
     * TODO grouping, execution_context, execution_id
     */
    public function __invoke(CacheMissed|CacheHit $event)
    {
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
            group: hash('sha256', ''),
            trace_id: $this->traceId,
            execution_context: 'request',
            execution_id: '00000000-0000-0000-0000-000000000000',
            user: (string) Auth::id(),
            store: $event->storeName,
            key: $event->key,
            type: $type,
        ));
    }
}
