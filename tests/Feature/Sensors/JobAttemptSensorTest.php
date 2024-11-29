<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    setDeploy('v1.2.3');
    setServerName('web-01');
    setPeakMemory(1234);
    setTraceId('00000000-0000-0000-0000-000000000000');
    setExecutionId('00000000-0000-0000-0000-000000000001');
    setExecutionStart(CarbonImmutable::parse('2000-01-01 01:02:03.456789'));

    Config::set('queue.default', 'database');
});

it('can ingest job attempts')
    ->with([
        'processed',
        'released',
        'failed',
    ])
    ->todo();

it('does not ingest jobs dispatched on the sync queue')->todo();

it('normalizes sqs queue names')->todo();

it('captures duration in microseconds')->todo();

it('captures closure job')->todo();

it('captures queued mail')->todo();

it('captures queued notification')->todo();
