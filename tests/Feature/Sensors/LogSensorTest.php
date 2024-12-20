<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

use function Orchestra\Testbench\Pest\defineEnvironment;
use function Pest\Laravel\get;

defineEnvironment(function () {
    forceRequestExecutionState();
});

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
            'context' => '{}',
            'extra' => '{}',
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

it('formats messages with replacement dates using configured format', function () {
    $ingest = fakeIngest();
    Route::get('/users', function () {
        Log::channel('nightwatch')->info('{datetime} - {datetimeimmutable} - {carbon} - {carbonimmutable}', [
            'datetime' => now()->toDateTime(),
            'datetimeimmutable' => now()->toDateTimeImmutable(),
            'carbon' => now(),
            'carbonimmutable' => now()->toImmutable(),
        ]);
    });

    $response = get('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('log:0.message', '2000-01-01 01:02:03.456789+00:00 - 2000-01-01 01:02:03.456789+00:00 - 2000-01-01 01:02:03.456789+00:00 - 2000-01-01 01:02:03.456789+00:00');
});

it('always logs UTC time', function () {
    $ingest = fakeIngest();
    Route::get('/users', function () {
        Log::channel('nightwatch')->info('{datetime} - {datetimeimmutable} - {carbon} - {carbonimmutable}', [
            'datetime' => now('Australia/Melbourne')->toDateTime(),
            'datetimeimmutable' => now('Australia/Melbourne')->toDateTimeImmutable(),
            'carbon' => now('Australia/Melbourne'),
            'carbonimmutable' => now('Australia/Melbourne')->toImmutable(),
        ]);
    });

    $response = get('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('log:0.message', '2000-01-01 01:02:03.456789+00:00 - 2000-01-01 01:02:03.456789+00:00 - 2000-01-01 01:02:03.456789+00:00 - 2000-01-01 01:02:03.456789+00:00');
});

it('does not mutate the date objects', function () {
    $ingest = fakeIngest();
    $datetime = now('Australia/Melbourne')->toDateTime();
    $datetimeImmutable = now('Australia/Melbourne')->toDateTimeImmutable();
    $carbon = now('Australia/Melbourne')->toMutable();
    $carbonImmutable = now('Australia/Melbourne')->toImmutable();
    Route::get('/users', function () use ($datetime, $datetimeImmutable, $carbon, $carbonImmutable) {
        Log::channel('nightwatch')->info('{datetime} - {datetimeimmutable} - {carbon} - {carbonimmutable}', [
            'datetime' => $datetime,
            'carbon' => $carbon,
            'datetimeimmutable' => $datetimeImmutable,
            'carbonimmutable' => $carbonImmutable,
        ]);
    });

    $response = get('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('log:0.message', '2000-01-01 01:02:03.456789+00:00 - 2000-01-01 01:02:03.456789+00:00 - 2000-01-01 01:02:03.456789+00:00 - 2000-01-01 01:02:03.456789+00:00');
    expect($datetime->getTimezone()->getName())->toBe('Australia/Melbourne');
    expect($carbon->getTimezone()->getName())->toBe('Australia/Melbourne');
    expect($datetimeImmutable->getTimezone()->getName())->toBe('Australia/Melbourne');
    expect($carbonImmutable->getTimezone()->getName())->toBe('Australia/Melbourne');
});

it('captures context', function () {
    $ingest = fakeIngest();
    Log::shareContext([
        'shared' => 'context',
    ]);
    Route::get('/users', function () {
        Log::channel('nightwatch')->info('Hello world!', [
            'context' => 'value',
            'date' => now(),
        ]);
    });

    $response = get('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('log:0.context', '{"shared":"context","context":"value","date":"2000-01-01 01:02:03.456789+00:00"}');
    $ingest->assertLatestWrite('log:0.extra', '{}');
});

it('captures extra', function () {
    $ingest = fakeIngest();
    Context::add('extra', 'context');
    Route::get('/users', function () {
        Log::channel('nightwatch')->info('Hello world!');
    });

    $response = get('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('log:0.extra', '{"extra":"context"}');
    $ingest->assertLatestWrite('log:0.context', '{}');
});

it('falls back to "single" log channel when error log is set to "nightwatch"', function () {
    $ingest = fakeIngest();
    $logs = [];
    Log::listen(function ($event) use (&$logs) {
        $logs[] = $event;
    });
    $e = new RuntimeException('Whoops!');
    Config::set('nightwatch.error_log_channel', 'nightwatch');
    Route::get('/', function () use ($e) {
        nightwatch()->handleUnrecoverableException($e);
    });

    $response = get('/');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('logs:*', []);
    expect($logs)->toHaveCount(1);
    expect($logs[0]->level)->toBe('critical');
    expect($logs[0]->message)->toBe('[nightwatch] Whoops!');
    expect($logs[0]->context)->toBe([
        'exception' => $e,
    ]);
});
