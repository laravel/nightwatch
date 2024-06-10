<?php

namespace Laravel\Nightwatch\Sensors;

use Carbon\CarbonImmutable;
use Illuminate\Auth\AuthManager;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Config\Repository as Config;
use Illuminate\Support\Facades\Auth;
use Laravel\Nightwatch\RecordCollection;

final class CacheEventsSensor
{
    public function __construct(
        private RecordCollection $records,
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

        // TODO: the cache events collection could be injected and then we
        // just modify it directly. Execution parent can also be injected.
        $this->records['cache_events'][] = [
            'timestamp' => $now->format('Y-m-d H:i:s'),
            'deploy_id' => $this->deployId,
            'server' => $this->server,
            'group' => hash('sha256', ''), // TODO
            'trace_id' => $this->traceId,
            'execution_context' => 'request', // TODO
            'execution_id' => '00000000-0000-0000-0000-000000000000', // TODO
            'user' => Auth::id() ?? '', // TODO allow this to be customised
            'store' => $event->storeName, // this can be nullable? fallback to default?
            'key' => $event->key,
            'type' => $type,
        ];

        $executionParent = $this->records['execution_parent'];

        $executionParent[$key] += 1;
    }
}
