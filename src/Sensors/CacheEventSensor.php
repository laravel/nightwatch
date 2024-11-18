<?php

namespace Laravel\Nightwatch\Sensors;

use Exception;
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

use function get_class;
use function hash;

/**
 * @internal
 */
final class CacheEventSensor
{
    /**
     * Holds the start times for cache events.
     *
     * @var array<string, float>
     */
    protected $startTimes = [];

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

        $eventType = match ($event::class) {
            RetrievingKey::class, CacheHit::class, CacheMissed::class => 'read',
            WritingKey::class, KeyWritten::class => 'write',
        };

        if ($event instanceof RetrievingKey || $event instanceof WritingKey) {
            $this->startTimes["{$eventType}:{$event->key}"] = $now;

            return;
        }

        $startTime = $this->startTimes["{$eventType}:{$event->key}"] ?? null;

        if ($startTime === null) {
            throw new Exception('No start time found for '.get_class($event)." event with key {$event->key}.");
        }

        unset($this->startTimes["{$eventType}:{$event->key}"]);

        [$type, $counter] = match ($event::class) {
            CacheHit::class => ['hit', 'cache_hits'],
            CacheMissed::class => ['miss', 'cache_misses'],
            KeyWritten::class => ['write', 'cache_writes'],
        };

        $this->executionState->{$counter}++;

        $this->recordsBuffer->writeCacheEvent(new CacheEvent(
            timestamp: $startTime,
            deploy: $this->executionState->deploy,
            server: $this->executionState->server,
            group: hash('md5', "{$event->storeName},{$event->key}"),
            trace_id: $this->executionState->trace,
            execution_context: $this->executionState->context,
            execution_id: $this->executionState->id,
            execution_stage: $this->executionState->stage,
            user: $this->user->id(),
            store: $event->storeName ?? '',
            key: $event->key,
            type: $type,
            duration: (int) $now - $startTime,
            ttl: $event instanceof KeyWritten ? $event->seconds : 0,
        ));
    }
}
