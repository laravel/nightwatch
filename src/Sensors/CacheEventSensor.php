<?php

namespace Laravel\Nightwatch\Sensors;

use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\Contracts\Clock;
use Laravel\Nightwatch\Records\CacheEvent;
use Laravel\Nightwatch\Records\ExecutionState;
use Laravel\Nightwatch\UserProvider;

/**
 * @internal
 */
final class CacheEventSensor
{
    public function __construct(
        private RecordsBuffer $recordsBuffer,
        private ExecutionState $executionState,
        private UserProvider $user,
        private Clock $clock,
        private string $server,
        private string $traceId,
    ) {
        //
    }

    /**
     * TODO grouping, execution_context, execution_id
     */
    public function __invoke(CacheMissed|CacheHit $event): void
    {
        $nowMicrotime = $this->clock->microtime();

        if ($event::class === CacheHit::class) {
            $type = 'hit';
            $this->executionState->cache_hits++;
        } else {
            $type = 'miss';
            $this->executionState->cache_misses++;
        }

        $this->recordsBuffer->writeCacheEvent(new CacheEvent(
            timestamp: (int) $nowMicrotime,
            deploy: $this->executionState->deploy,
            server: $this->server,
            group: hash('sha256', ''),
            trace_id: $this->traceId,
            execution_context: 'request',
            execution_id: '00000000-0000-0000-0000-000000000000',
            execution_offset: $this->clock->executionOffset($nowMicrotime),
            user: $this->user->id(),
            store: $event->storeName ?? '',
            key: $event->key,
            type: $type,
        ));
    }
}
