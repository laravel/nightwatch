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
        $nowMicrotime = $this->clock->microtime();

        if ($event instanceof RetrievingKey || $event instanceof WritingKey) {
            $eventType = match ($event::class) {
                RetrievingKey::class => 'retrieving',
                WritingKey::class => 'writing',
            };
            $this->startTimes["{$eventType}:{$event->key}"] = $nowMicrotime;

            return;
        }

        [$type, $counter] = match ($event::class) {
            CacheHit::class => ['hit', 'cache_hits'],
            CacheMissed::class => ['miss', 'cache_misses'],
            KeyWritten::class => ['write', 'cache_writes'],
        };

        $this->executionState->{$counter}++;

        $this->recordsBuffer->writeCacheEvent(new CacheEvent(
            timestamp: (int) $nowMicrotime,
            deploy: $this->executionState->deploy,
            server: $this->executionState->server,
            group: hash('md5', "{$event->storeName},{$event->key}"),
            trace_id: $this->executionState->trace,
            execution_context: 'request',
            execution_id: '00000000-0000-0000-0000-000000000000',
            execution_stage: $this->executionState->stage,
            user: $this->user->id(),
            store: $event->storeName ?? '',
            key: $event->key,
            type: $type,
            duration: $this->getDuration($event, $nowMicrotime),
            ttl: $event instanceof KeyWritten ? $event->seconds : 0,
        ));
    }

    private function getDuration(CacheHit|CacheMissed|KeyWritten $event, float $nowMicrotime): int
    {
        $eventType = match ($event::class) {
            CacheHit::class, CacheMissed::class => 'retrieving',
            KeyWritten::class => 'writing',
        };

        $startTime = $this->startTimes["{$eventType}:{$event->key}"] ?? null;

        if ($startTime === null) {
            return 0;
        }

        unset($this->startTimes["{$eventType}:{$event->key}"]);

        return (int) $nowMicrotime - $startTime;
    }
}
