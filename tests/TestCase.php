<?php

namespace Tests;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Compatibility;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

use function collect;
use function env;
use function Illuminate\Filesystem\join_paths;
use function now;
use function touch;

abstract class TestCase extends OrchestraTestCase
{
    use RefreshDatabase, WithWorkbench;

    protected Core $core;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        Nightwatch::handleUnrecoverableExceptionsUsing(fn ($e) => throw $e);
        Compatibility::$context = [];

        $this->core = $this->app->make(Core::class);
        $this->core->state->flush();
        $this->core->ingest->flush();
        $this->core->clock->microtimeResolver = fn () => (float) now()->format('U.u');
    }

    protected function tearDown(): void
    {
        Str::createUuidsNormally();

        unset($this->core);

        parent::tearDown();
    }

    protected function beforeRefreshingDatabase(): void
    {
        touch(env('DB_DATABASE'));
    }

    protected function prependListener(string $event, callable $listener): void
    {
        $listeners = $this->app['events']->getRawListeners()[$event] ?? [];

        $this->app['events']->forget($event);

        collect([$listener, ...$listeners])->each(fn ($listener) => $this->app['events']->listen($event, $listener));
    }

    protected function fixturePath(string $path): string
    {
        return join_paths(__DIR__, 'fixtures', $path);
    }

    protected function forceRequestExecutionState(): void
    {
        Env::getRepository()->set('NIGHTWATCH_FORCE_REQUEST', '1');
        Env::getRepository()->clear('NIGHTWATCH_FORCE_COMMAND');
    }

    protected function forceCommandExecutionState(): void
    {
        Env::getRepository()->set('NIGHTWATCH_FORCE_COMMAND', '1');
        Env::getRepository()->clear('NIGHTWATCH_FORCE_REQUEST');
    }

    protected function fakeIngest(?FakeIngest $fake = null): FakeIngest
    {
        $this->core->sensor->flush();

        return $this->core->ingest = $this->core->sensor->ingest = $fake ?? new FakeIngest;
    }

    protected function setDeploy(string $deploy): void
    {
        $this->core->state->deploy = $deploy;
    }

    protected function setServerName(string $server): void
    {
        $this->core->state->server = $server;
    }

    protected function setPeakMemory(int $value): void
    {
        $this->core->state->peakMemoryResolver = fn () => $value;
    }

    protected function setTraceId(string $traceId): void
    {
        $this->core->state->trace = $traceId;

        Compatibility::addHiddenContext('nightwatch_trace_id', $traceId);
    }

    protected function setExecutionStart(CarbonImmutable $timestamp): void
    {
        $this->syncClock($timestamp);
        $this->core->state->stageDurations[ExecutionStage::Bootstrap->value] = 0;
        $this->core->state->currentExecutionStageStartedAtMicrotime = (float) $timestamp->format('U.u');
        $this->core->state->stage = match ($this->core->state::class) {
            RequestState::class => ExecutionStage::BeforeMiddleware,
            CommandState::class => ExecutionStage::Action,
        };
    }

    protected function setExecutionId(string $executionId): void
    {
        $this->core->state->setId($executionId);
    }

    protected function syncClock(DateTimeInterface $timestamp): void
    {
        $this->core->state->timestamp = (float) $timestamp->format('U.u');

        $this->travelTo($timestamp);
    }

    protected function setPhpVersion(string $version): void
    {
        $this->core->state->phpVersion = $version;
    }

    protected function setLaravelVersion(string $version): void
    {
        $this->core->state->laravelVersion = $version;
    }
}
