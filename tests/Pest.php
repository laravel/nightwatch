<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Event;
use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Contracts\LocalIngest;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Location;
use Laravel\Nightwatch\Records\ExecutionState;
use Tests\FakeIngest;

use function Illuminate\Filesystem\join_paths;
use function Pest\Laravel\travelTo;

pest()->extends(Tests\TestCase::class)->beforeEach(function () {
    app(Clock::class)->microtimeResolver = fn () => (float) now()->format('U.u');
    app()->setBasePath($path = realpath(__DIR__.'/../'));
    app(Location::class)->setBasePath($path)->setPublicPath("{$path}/public");
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
    app(Clock::class)->executionStartInMicrotime = (float) $timestamp->format('U.u');
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
    app(ExecutionState::class)->peakMemoryResolver = fn () => $value;
}

function fakeIngest(): FakeIngest
{
    return App::instance(LocalIngest::class, new FakeIngest);
}

function afterMigrations(Closure $callback)
{
    if (RefreshDatabaseState::$migrated) {
        $callback();
    } else {
        Event::listen(MigrationsEnded::class, $callback);
    }
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
