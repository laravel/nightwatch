<?php

namespace Laravel\Nightwatch\Sensors;

use Illuminate\Cache\Events\CacheEvent;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\ForgettingKey;
use Illuminate\Cache\Events\KeyForgotten;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Cache\Events\RetrievingKey;
use Illuminate\Cache\Events\WritingKey;
use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Records\CacheEvent as CacheEventRecord;
use Laravel\Nightwatch\Records\ExecutionState;
use Laravel\Nightwatch\UserProvider;
use RuntimeException;

use function hash;
use function round;

/**
 * @internal
 */
final class CacheEventSensor
{
    private ?float $startTime = null;

    public function __construct(
        private Clock $clock,
        private ExecutionState $executionState,
        private RecordsBuffer $recordsBuffer,
        private UserProvider $user,
    ) {
        //
    }

    public function __invoke(CacheEvent $event): void
    {
        $now = $this->clock->microtime();
        $class = $event::class;

        if ($class === RetrievingKey::class || $class === WritingKey::class || $class === ForgettingKey::class) {
            $this->startTime = $now;

            return;
        }

        if ($this->startTime === null) {
            throw new RuntimeException("No start time found for [{$class}] event with key [{$event->key}].");
        }

        if ($class === CacheHit::class) {
            $type = 'hit';
            $this->executionState->cache_hits++;
        } elseif ($class === CacheMissed::class) {
            $type = 'miss';
            $this->executionState->cache_misses++;
        } elseif ($class === KeyWritten::class) {
            $type = 'write';
            $this->executionState->cache_writes++;
        } elseif ($class === KeyForgotten::class) {
            $type = 'forget';
            $this->executionState->cache_forgets++;
        } else {
            throw new RuntimeException("Unexpected event type [{$class}].");
        }

        $this->recordsBuffer->write(new CacheEventRecord(
            timestamp: $this->startTime,
            deploy: $this->executionState->deploy,
            server: $this->executionState->server,
            _group: hash('md5', "{$event->storeName},{$event->key}"),
            trace_id: $this->executionState->trace,
            execution_context: $this->executionState->context,
            execution_id: $this->executionState->id,
            execution_stage: $this->executionState->stage,
            user: $this->user->id(),
            store: $event->storeName ?? '',
            key: $event->key,
            type: $type,
            duration: (int) round(($now - $this->startTime) * 1_000_000),
            ttl: $event instanceof KeyWritten ? ($event->seconds ?? 0) : 0,
        ));
    }
}
