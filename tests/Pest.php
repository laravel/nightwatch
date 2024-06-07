<?php

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Laravel\Nightwatch\Contracts\Ingest;
use Laravel\Nightwatch\Contracts\PeakMemoryProvider;
use Laravel\Nightwatch\RecordCollection;
use Laravel\Nightwatch\TraceId;
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
    App::singleton(TraceId::class, fn () => new TraceId($traceId));
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
