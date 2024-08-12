<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Event;
use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\Clock as NightwatchClock;
use Laravel\Nightwatch\Contracts\Ingest;
use Laravel\Nightwatch\Contracts\PeakMemoryProvider;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Records\ExecutionState;
use Tests\FakeIngest;

use function Illuminate\Filesystem\join_paths;
use function Pest\Laravel\travelTo;

pest()->extends(Tests\TestCase::class)->beforeEach(function () {
    app(NightwatchClock::class)->microtimeResolver = fn () => (float) now()->format('U.u');
});

function setExecutionStart(CarbonImmutable $timestamp): void
{
    syncClock($timestamp);
    app(ExecutionState::class)->currentExecutionStageStartedAtMicrotime = (float) $timestamp->format('U.u');
    app(ExecutionState::class)->stage = ExecutionStage::BeforeMiddleware;
    app(ExecutionState::class)->currentExecutionStageStartedAtMicrotime = (float) $timestamp->format('U.u');
    app(ExecutionState::class)->stageDurations[ExecutionStage::Bootstrap->value] = 0;
}

function syncClock(DateTimeInterface $timestamp): void
{
    app(NightwatchClock::class)->executionStartInMicrotime = (float) $timestamp->format('U.u');
    travelTo($timestamp);
}

function records(): RecordsBuffer
{
    return app(RecordsBuffer::class);
}

function setDeploy(string $deploy): void
{
    app(ExecutionState::class)->deploy = $deploy;
}

function setServerName(string $server): void
{
    app(ExecutionState::class)->server = $server;
}

function setTraceId(string $traceId): void
{
    app(ExecutionState::class)->trace = $traceId;
}

function setExecutionId(string $executionId): void
{
    app(ExecutionState::class)->id = $executionId;
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
