<?php

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Laravel\Nightwatch\Contracts\Ingest;
use Laravel\Nightwatch\Contracts\PeakMemoryProvider;
use Laravel\Nightwatch\RecordCollection;
use Tests\FakeIngest;

uses(Tests\TestCase::class)->in('Feature');

function records(): RecordCollection
{
    return App::make(RecordCollection::class);
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
