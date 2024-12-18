<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

use function Pest\Laravel\get;

beforeEach(function () {
    setDeploy('v1.2.3');
    setServerName('web-01');
    setPeakMemory(1234);
    setTraceId('00000000-0000-0000-0000-000000000000');
    setExecutionId('00000000-0000-0000-0000-000000000001');
    setExecutionStart(CarbonImmutable::parse('2000-01-01 01:02:03.456789'));
});

it('ingests logs', function () {
    $ingest = fakeIngest();
    Route::get('/users', function () {
        Log::channel('nightwatch')->info('hello world');
    });

    $response = get('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('request:0.logs', 1);
    $ingest->assertLatestWrite('log:*', function (array $records) {
        expect($records)->toHaveCount(1);
        expect($records[0])->toHaveKey('timestamp');
        expect($records[0]['timestamp'])->toBeFloat();
        expect($records[0]['timestamp'])->toEqualWithDelta(microtime(true), 0.1);
        expect(Arr::except($records[0], 'timestamp'))->toBe([
            'v' => 1,
            't' => 'log',
            'deploy' => 'v1.2.3',
            'server' => 'web-01',
            'trace_id' => '00000000-0000-0000-0000-000000000000',
            'execution_source' => 'request',
            'execution_id' => '00000000-0000-0000-0000-000000000001',
            'execution_stage' => 'action',
            'user' => '',
            'level' => 'info',
            'message' => 'hello world',
            'context' => [],
            'extra' => [],
        ]);

        return true;
    });
});

it('formats messages with replacements', function () {
    $ingest = fakeIngest();
    Route::get('/users', function () {
        Log::channel('nightwatch')->info('hello {location}', [
            'location' => 'world',
        ]);
    });

    $response = get('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('log:0.message', 'hello world');
});

it('timestamp is always in UTC', function () {
    $ingest = fakeIngest();
    Route::get('/users', function () {
        Log::channel('nightwatch')->info('hello {location}', [
            'location' => 'world',
        ]);
    });

    $response = get('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('log:0.message', 'hello world');
});

it('formats messages with replacement dates using configured format', function () {
    $ingest = fakeIngest();
    Route::get('/users', function () {
        Log::channel('nightwatch')->info('datetime: {datetime}; carbon: {carbon}', [
            'datetime' => now()->toDateTime(),
            'carbon' => now(),
        ]);
    });

    $response = get('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('log:0.message', 'datetime: 2000-01-01 01:02:03.456789; carbon: 2000-01-01 01:02:03.456789');
});

it('always logs UTC time', function () {
    $ingest = fakeIngest();
    Route::get('/users', function () {
        Log::channel('nightwatch')->info('datetime: {datetime}; carbon: {carbon}', [
            'datetime' => now('Australia/Melbourne')->toDateTime(),
            'carbon' => now('Australia/Melbourne'),
        ]);
    });

    $response = get('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('log:0.message', 'datetime: 2000-01-01 01:02:03.456789; carbon: 2000-01-01 01:02:03.456789');
});

it('does not mutate the date objects', function () {
    $ingest = fakeIngest();
    $datetime = now('Australia/Melbourne')->toDateTime();
    $datetimeImmutable = now('Australia/Melbourne')->toDateTimeImmutable();
    $carbon = now('Australia/Melbourne')->toMutable();
    $carbonImmutable = now('Australia/Melbourne')->toImmutable();
    Route::get('/users', function () use ($datetime, $datetimeImmutable, $carbon, $carbonImmutable) {
        Log::channel('nightwatch')->info('datetime: {datetime}; carbon: {carbon}', [
            'datetime' => $datetime,
            'carbon' => $carbon,
            'DateTimeImmutable' => $datetimeImmutable,
            'CarbonImmutable' => $carbonImmutable,
        ]);
    });

    $response = get('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    expect($datetime->getTimezone()->getName())->toBe('Australia/Melbourne');
    expect($carbon->getTimezone()->getName())->toBe('Australia/Melbourne');
    expect($datetimeImmutable->getTimezone()->getName())->toBe('Australia/Melbourne');
    expect($carbonImmutable->getTimezone()->getName())->toBe('Australia/Melbourne');

    // $ingest->assertLatestWrite('request:0.logs', 1);
    $ingest->assertLatestWrite('log:*', function (array $records) {
        expect($records)->toHaveCount(1);
        expect($records[0])->toHaveKey('timestamp');
        expect($records[0]['timestamp'])->toBeFloat();
        expect($records[0]['timestamp'])->toEqualWithDelta(microtime(true), 0.1);
        expect(Arr::except($records[0], 'timestamp'))->toBe([
            'v' => 1,
            't' => 'log',
            'deploy' => 'v1.2.3',
            'server' => 'web-01',
            'trace_id' => '00000000-0000-0000-0000-000000000000',
            'execution_context' => 'request',
            'execution_id' => '00000000-0000-0000-0000-000000000001',
            'execution_stage' => 'action',
            'user' => '',
            'level' => 'info',
            'message' => 'hello world',
            'context' => [],
            'extra' => [],
        ]);

        return true;
    });

    $ingest->assertLatestWrite('log:0.message', 'date: 2000-01-01 01:02:03.456789');
});
