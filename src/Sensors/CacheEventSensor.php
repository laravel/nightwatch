<?php

namespace Laravel\Nightwatch\Sensors;

use Illuminate\Cache\Events\CacheEvent;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\ForgettingKey;
use Illuminate\Cache\Events\KeyForgetFailed;
use Illuminate\Cache\Events\KeyForgotten;
use Illuminate\Cache\Events\KeyWriteFailed;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Cache\Events\RetrievingKey;
use Illuminate\Cache\Events\RetrievingManyKeys;
use Illuminate\Cache\Events\WritingKey;
use Illuminate\Cache\Events\WritingManyKeys;
use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Records\CacheEvent as CacheEventRecord;
use Laravel\Nightwatch\Records\ExecutionState;
use Laravel\Nightwatch\UserProvider;
use RuntimeException;

use function hash;
use function in_array;
use function round;

/**
 * @internal
 */
final class CacheEventSensor
{
    private ?float $startTime = null;

    private ?int $duration = null;

    private const START_EVENTS = [
        RetrievingKey::class,
        RetrievingManyKeys::class,
        WritingKey::class,
        WritingManyKeys::class,
        ForgettingKey::class,
    ];

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

        if (in_array($event::class, self::START_EVENTS, strict: true)) {
            $this->startTime = $now;
            $this->duration = null;

            return;
        }

        if ($this->startTime === null) {
            throw new RuntimeException('No start time found for ['.$event::class."] event with key [{$event->key}].");
        }

        $this->duration ??= (int) round(($now - $this->startTime) * 1_000_000);
        $this->executionState->cache_events++;

        $type = match ($event::class) {
            CacheHit::class => 'hit',
            CacheMissed::class => 'miss',
            KeyWritten::class => 'write',
            KeyWriteFailed::class => 'write-failure',
            KeyForgotten::class => 'forget',
            KeyForgetFailed::class => 'forget-failure',
            default => throw new RuntimeException('Unexpected event type ['.$event::class.']'),
        };

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
            duration: $this->duration,
            ttl: $event::class === KeyWritten::class ? ($event->seconds ?? 0) : 0,
        ));
    }
}
