<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Contracts\LocalIngest;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Location;
use Laravel\Nightwatch\State\RequestState;
use Tests\FakeIngest;

use function Illuminate\Filesystem\join_paths;
use function Pest\Laravel\travelTo;

pest()->extends(Tests\TestCase::class)->beforeEach(function () {
    app(Clock::class)->microtimeResolver = fn () => (float) now()->format('U.u');
    app()->setBasePath($path = realpath(__DIR__.'/../'));
    app(Location::class)->setBasePath($path)->setPublicPath("{$path}/public");
});

function setRequestStart(CarbonImmutable $timestamp): void
{
    syncClock($timestamp);
    app(RequestState::class)->stageDurations[ExecutionStage::Bootstrap->value] = 0;
    app(RequestState::class)->stage = ExecutionStage::BeforeMiddleware;
    app(RequestState::class)->currentExecutionStageStartedAtMicrotime = (float) $timestamp->format('U.u');
}

function syncClock(DateTimeInterface $timestamp): void
{
    app(RequestState::class)->timestamp = (float) $timestamp->format('U.u');
    travelTo($timestamp);
}

function setDeploy(string $deploy): void
{
    app(RequestState::class)->deploy = $deploy;
}

function setServerName(string $server): void
{
    app(RequestState::class)->server = $server;
}

function setTraceId(string $traceId): void
{
    app(RequestState::class)->trace = $traceId;
}

function setExecutionId(string $executionId): void
{
    app(RequestState::class)->id = $executionId;
}

function setPeakMemory(int $value): void
{
    app(RequestState::class)->peakMemoryResolver = fn () => $value;
}

function setLaravelVersion(string $version): void
{
    app(RequestState::class)->laravelVersion = $version;
}

function setPhpVersion(string $version): void
{
    app(RequestState::class)->phpVersion = $version;
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
