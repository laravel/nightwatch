<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Laravel\Nightwatch\Contracts\LocalIngest;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Location;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Tests\FakeIngest;

use function Illuminate\Filesystem\join_paths;
use function Pest\Laravel\travelTo;

pest()->extends(Tests\TestCase::class)->beforeEach(function () {
    app(Core::class)->clock->microtimeResolver = fn () => (float) now()->format('U.u');
    app()->setBasePath($path = realpath(__DIR__.'/../'));
    app(Location::class)->setBasePath($path)->setPublicPath("{$path}/public");
    Config::set('nightwatch.error_log_channel', 'null');
});

function forceRequestExecutionState(): void
{
    Env::getRepository()->set('NIGHTWATCH_FORCE_REQUEST', '1');
    Env::getRepository()->clear('NIGHTWATCH_FORCE_COMMAND');
}

function forceCommandExecutionState(): void
{
    Env::getRepository()->set('NIGHTWATCH_FORCE_COMMAND', '1');
    Env::getRepository()->clear('NIGHTWATCH_FORCE_REQUEST');
}

function executionState(): RequestState|CommandState
{
    return match (true) {
        (bool) Env::get('NIGHTWATCH_FORCE_REQUEST') => app(RequestState::class),
        (bool) Env::get('NIGHTWATCH_FORCE_COMMAND') => app(CommandState::class),
        default => throw new RuntimeException('Unknown execution state type. Make sure to call the `forceRequestExecutionState` or `forceCommandExecutionState` function.')
    };
}

function setExecutionStart(CarbonImmutable $timestamp): void
{
    match (executionState()::class) {
        RequestState::class => setRequestStart($timestamp),
        CommandState::class => setCommandStart($timestamp),
    };
}

function setRequestStart(CarbonImmutable $timestamp): void
{
    syncClock($timestamp);
    app(RequestState::class)->stageDurations[ExecutionStage::Bootstrap->value] = 0;
    app(RequestState::class)->stage = ExecutionStage::BeforeMiddleware;
    app(RequestState::class)->currentExecutionStageStartedAtMicrotime = (float) $timestamp->format('U.u');
}

function setCommandStart(CarbonImmutable $timestamp): void
{
    syncClock($timestamp);
    app(CommandState::class)->stageDurations[ExecutionStage::Bootstrap->value] = 0;
    app(CommandState::class)->stage = ExecutionStage::Action;
    app(CommandState::class)->currentExecutionStageStartedAtMicrotime = (float) $timestamp->format('U.u');
}

function syncClock(DateTimeInterface $timestamp): void
{
    executionState()->timestamp = (float) $timestamp->format('U.u');
    travelTo($timestamp);
}

function setDeploy(string $deploy): void
{
    executionState()->deploy = $deploy;
}

function setServerName(string $server): void
{
    executionState()->server = $server;
}

function setTraceId(string $traceId): void
{
    executionState()->trace = $traceId;
}

function setExecutionId(string $executionId): void
{
    executionState()->id = $executionId;
}

function setPeakMemory(int $value): void
{
    executionState()->peakMemoryResolver = fn () => $value;
}

function setLaravelVersion(string $version): void
{
    executionState()->laravelVersion = $version;
}

function setPhpVersion(string $version): void
{
    executionState()->phpVersion = $version;
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
