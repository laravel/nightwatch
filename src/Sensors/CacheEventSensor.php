<?php

namespace Laravel\Nightwatch\Sensors;

use Carbon\CarbonImmutable;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Support\Facades\Auth;
use Laravel\Nightwatch\Records;
use Laravel\Nightwatch\Records\CacheEvent;

final class CacheEventSensor
{
    public function __construct(
        private Records $records,
        private string $deployId,
        private string $server,
        private string $traceId,
    ) {
        //
    }

    // TODO: "tags"?
    public function __invoke(CacheMissed|CacheHit $event)
    {
        $now = CarbonImmutable::now();

        [$type, $key] = match ($event::class) {
            CacheMissed::class => ['miss', 'cache_misses'],
            CacheHit::class => ['hit', 'cache_hits'],
        };

        // TODO limit length of keys when needed for validation
        // TODO: the cache events collection could be injected and then we
        // just modify it directly. Execution parent can also be injected.
        $this->records->addCacheEvent(new CacheEvent(
            timestamp: $now->format('Y-m-d H:i:s'),
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
