<?php

namespace Laravel\Nightwatch\Sensors;

use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Cache\Events\RetrievingKey;
use Illuminate\Cache\Events\WritingKey;
use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Records\CacheEvent;
use Laravel\Nightwatch\Records\ExecutionState;
use Laravel\Nightwatch\UserProvider;
use RuntimeException;

use function hash;

/**
 * @internal
 */
final class CacheEventSensor
{
    /**
     * @var array<class-string<RetrievingKey|CacheHit|CacheMissed|WritingKey|KeyWritten>, array<string, float>>
     */
    private array $startTimes = [
        RetrievingKey::class => [],
        CacheHit::class => [],
        CacheMissed::class => [],
        WritingKey::class => [],
        KeyWritten::class => [],
    ];

    public function __construct(
        private Clock $clock,
        private ExecutionState $executionState,
        private RecordsBuffer $recordsBuffer,
        private UserProvider $user,
    ) {
        //
    }

    public function __invoke(RetrievingKey|CacheHit|CacheMissed|WritingKey|KeyWritten $event): void
    {
        $now = $this->clock->microtime();
        $class = $event::class;

        $eventType = match ($class) {
            RetrievingKey::class, CacheHit::class, CacheMissed::class => 'read',
            WritingKey::class, KeyWritten::class => 'write',
            default => throw new RuntimeException("Unexpected cache-event type [{$class}]."),
        };

        if ($class === RetrievingKey::class || $class === WritingKey::class) {
            $this->startTimes[$eventType][$event->key] = $now;

            return;
        }

        $startTime = $this->startTimes[$eventType][$event->key] ?? null;

        if ($startTime === null) {
            throw new RuntimeException("No start time found for [{$class}] event with key [{$event->key}].");
        }

        unset($this->startTimes[$eventType][$event->key]);

        if ($class === CacheHit::class) {
            $type = 'hit';
            $this->executionState->cache_hits++;
        } elseif ($class === CacheMissed::class) {
            $type = 'hit';
            $this->executionState->cache_misses++;
        } elseif ($class === KeyWritten::class) {
            $type = 'write';
            $this->executionState->cache_writes++;
        }

        $this->recordsBuffer->write(new CacheEvent(
            timestamp: $startTime,
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
            duration: (int) ($now - $startTime),
            ttl: $event instanceof KeyWritten ? ($event->seconds ?? 0) : 0,
        ));
    }
}
