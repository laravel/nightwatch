<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\Contracts\Clock;
use Laravel\Nightwatch\Contracts\Ingest;
use Laravel\Nightwatch\Contracts\PeakMemoryProvider;
use Laravel\Nightwatch\SensorManager;
use Tests\FakeIngest;

use function Illuminate\Filesystem\join_paths;
use function Pest\Laravel\travelTo;

uses(Tests\TestCase::class)->in('Feature', 'Unit');

function syncClock(CarbonImmutable $timestamp): void
{
    travelTo($timestamp);

    $executionStartInMicroseconds = (int) $timestamp->format('Uu');

    app(SensorManager::class)->setClock(new class($executionStartInMicroseconds) implements Clock
    {
        public function __construct(private int $executionStartInMicroseconds)
        {
            //
        }

        public function nowInMicroseconds(): int
        {
            return (int) now()->format('Uu');
        }

        public function diffInMicrotime(float $start): float
        {
            return $this->microtime() - $start;
        }

        public function executionStartInMicroseconds(): int
        {
            return $this->executionStartInMicroseconds;
        }
    });
}

function records(): RecordsBuffer
{
    return App::make(RecordsBuffer::class);
}

function setDeployId(string $deployId): void
{
    Config::set('nightwatch.deploy_id', $deployId);
}

function setServerName(string $name): void
{
    Config::set('nightwatch.server', $name);
}

function setTraceId(string $traceId): void
{
    App::instance('laravel.nightwatch.trace_id', $traceId);
}

function setPeakMemoryInKilobytes(int $value): void
{
    App::singleton(PeakMemoryProvider::class, fn () => new class($value) implements PeakMemoryProvider
    {
        public function __construct(private int $kilobytes)
        {
            //
        }

        public function kilobytes(): int
        {
            return $this->kilobytes;
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

function ignoreMigrationQueries() {
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
