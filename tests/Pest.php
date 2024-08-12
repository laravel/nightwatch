<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Event;
use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\Contracts\Clock;
use Laravel\Nightwatch\Contracts\Ingest;
use Laravel\Nightwatch\Contracts\PeakMemoryProvider;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\SensorManager;
use Tests\FakeIngest;

use function Illuminate\Filesystem\join_paths;
use function Pest\Laravel\travelTo;

pest()->extends(Tests\TestCase::class);

function syncClock(CarbonImmutable $timestamp): void
{
    travelTo($timestamp);

    $executionStartInMicrotime = (float) $timestamp->format('U.u');

    $sensor = app(SensorManager::class);
    $sensor->prepareForNextInvocation();
    $sensor->setClock($clock = new class($executionStartInMicrotime) implements Clock
    {
        public function __construct(private float $executionStartInMicrotime)
        {
            //
        }

        public function microtime(): float
        {
            return now()->getPreciseTimestamp(6) / 1_000_000;
        }

        public function diffInMicrotime(float $start): float
        {
            return $this->microtime() - $start;
        }

        public function executionStartInMicrotime(): float
        {
            return $this->executionStartInMicrotime;
        }
    });
    App::instance(Clock::class, $clock);
    $sensor->stage(ExecutionStage::BeforeMiddleware);
}

function records(): RecordsBuffer
{
    return App::make(RecordsBuffer::class);
}

function setDeploy(string $deploy): void
{
    app(SensorManager::class)->executionState->deploy = $deploy;
}

function setServerName(string $server): void
{
    app(SensorManager::class)->executionState->server = $server;
}

function setTraceId(string $traceId): void
{
    app(SensorManager::class)->executionState->trace = $traceId;
}

function setExecutionId(string $executionId): void
{
    app(SensorManager::class)->executionState->id = $executionId;
}

function setPeakMemory(int $value): void
{
    App::singleton(PeakMemoryProvider::class, fn () => new class($value) implements PeakMemoryProvider
    {
        public function __construct(private int $bytes)
        {
            //
        }

        public function bytes(): int
        {
            return $this->bytes;
        }
    });
}

function fakeIngest(): FakeIngest
{
    return App::instance(Ingest::class, new FakeIngest);
}

function prependListener(string $event, callable $listener): void
{
    $listeners = Event::getRawListeners()[$event];

    Event::forget($event);

    collect([$listener, ...$listeners])->each(fn ($listener) => Event::listen($event, $listener));
}

function ignoreMigrationQueries()
{
    prependListener(QueryExecuted::class, function () {
        if (! RefreshDatabaseState::$migrated) {
            return false;
        }
    });
}

function fixturePath(string $path): string
{
    return join_paths(__DIR__, 'fixtures', $path);
}
