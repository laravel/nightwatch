<?php

namespace Tests;

use BadMethodCallException;
use Carbon\CarbonImmutable;
use Closure;
use DateTimeInterface;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Compatibility;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Ingest;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use PHPUnit\Framework\ExpectationFailedException;

use function array_combine;
use function array_intersect_key;
use function collect;
use function env;
use function fopen;
use function Illuminate\Filesystem\join_paths;
use function now;
use function realpath;
use function sprintf;
use function stream_wrapper_register;
use function stream_wrapper_unregister;
use function touch;

abstract class TestCase extends OrchestraTestCase
{
    use RefreshDatabase, WithWorkbench;

    protected Core $core;

    protected function setUp(): void
    {
        $_ENV['APP_BASE_PATH'] = realpath(__DIR__.'/../workbench/').'/';

        parent::setUp();

        Http::preventStrayRequests();

        Nightwatch::handleUnrecoverableExceptionsUsing(fn ($e) => throw $e);
        Compatibility::$context = [];

        $this->core = $this->app->make(Core::class);
        $this->core->flush();
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

    /**
     * @param  (callable(Ingest, Collection): FakeIngest)  $callback
     */
    protected function fakeIngest(?Closure $callback = null): FakeIngest
    {
        $this->core->sensor->flush();

        $callback ??= fn ($ingest, $streams) => new FakeIngest($ingest, $streams);
        $streams = $this->fakeTcpStreams();

        return $this->core->ingest = $callback($this->core->ingest, $streams);
    }

    /**
     * @return Collection<FakeTcpStream>
     */
    protected function fakeTcpStreams(): Collection
    {
        stream_wrapper_register('tcp', FakeTcpStream::class);
        $this->core->ingest->streamFactory = fn ($address, $timeout) => fopen($address, 'r+');

        $this->beforeApplicationDestroyed(function () {
            stream_wrapper_unregister('tcp');
            FakeTcpStream::flush();
        });

        return FakeTcpStream::instances();
    }

    protected function setDeploy(string $deploy): void
    {
        $this->core->executionState->deploy = $deploy;
    }

    protected function setServerName(string $server): void
    {
        $this->core->executionState->server = $server;
    }

    protected function setPeakMemory(int $value): void
    {
        $this->core->executionState->peakMemoryResolver = fn () => $value;
    }

    protected function setTraceId(string $traceId): void
    {
        $this->core->executionState->trace = $traceId;

        Compatibility::addHiddenContext('nightwatch_trace_id', $traceId);
    }

    protected function setExecutionStart(CarbonImmutable $timestamp): void
    {
        $this->syncClock($timestamp);
        $this->core->executionState->stageDurations[ExecutionStage::Bootstrap->value] = 0;
        $this->core->executionState->currentExecutionStageStartedAtMicrotime = (float) $timestamp->format('U.u');
        $this->core->executionState->stage = match ($this->core->executionState::class) {
            RequestState::class => ExecutionStage::BeforeMiddleware,
            CommandState::class => ExecutionStage::Action,
        };
    }

    protected function setExecutionId(string $executionId): void
    {
        $this->core->executionState->setId($executionId);
    }

    protected function syncClock(DateTimeInterface $timestamp): void
    {
        $this->core->executionState->timestamp = (float) $timestamp->format('U.u');

        $this->travelTo($timestamp);
    }

    protected function setPhpVersion(string $version): void
    {
        $this->core->executionState->phpVersion = $version;
    }

    protected function setLaravelVersion(string $version): void
    {
        $this->core->executionState->laravelVersion = $version;
    }

    public function __call(string $method, array $arguments): mixed
    {
        if ($method === 'assertArrayIsIdenticalToArrayOnlyConsideringListOfKeys') {
            $this->backfilledAssertArrayIsIdenticalToArrayOnlyConsideringListOfKeys(...$arguments);

            return null;
        }

        throw new BadMethodCallException(sprintf(
            'Call to undefined method %s::%s()', static::class, $method
        ));
    }

    /**
     * Asserts that two arrays are identical while only considering a list of keys.
     *
     * NOTE Backfilled to support lower versions of PHPUnit.
     *
     * @param  array<mixed>  $expected
     * @param  array<mixed>  $actual
     * @param  non-empty-list<array-key>  $keysToBeConsidered
     *
     * @throws Exception
     * @throws ExpectationFailedException
     */
    public static function backfilledAssertArrayIsIdenticalToArrayOnlyConsideringListOfKeys(array $expected, array $actual, array $keysToBeConsidered, string $message = ''): void
    {
        $keysToBeConsidered = array_combine($keysToBeConsidered, $keysToBeConsidered);
        $expected = array_intersect_key($expected, $keysToBeConsidered);
        $actual = array_intersect_key($actual, $keysToBeConsidered);

        self::assertSame($expected, $actual, $message);
    }
}
