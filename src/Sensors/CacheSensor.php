<?php

namespace Laravel\Nightwatch\Sensors;

use Carbon\CarbonImmutable;
use Illuminate\Auth\AuthManager;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Config\Repository as Config;
use Laravel\Nightwatch\RecordCollection;
use Laravel\Nightwatch\TraceId;

final class CacheSensor
{
    public function __construct(
        private RecordCollection $records,
        private Config $config,
        private TraceId $traceId,
        private AuthManager $auth,
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
            'deploy_id' => (string) $this->config->get('nightwatch.deploy_id'),
            'server' => $this->config->get('nightwatch.server'),
            'group' => hash('sha256', ''), // TODO
            'trace_id' => $this->traceId->value(),
            'execution_context' => 'request', // TODO
            'execution_id' => '00000000-0000-0000-0000-000000000000', // TODO
            'user' => $this->auth->id() ?? '',
            'store' => $event->storeName, // this can be nullable? fallback to default?
            'key' => $event->key,
            'type' => $type,
        ];

        $executionParent = $this->records['execution_parent'];

        $executionParent[$key] += 1;
    }
}
