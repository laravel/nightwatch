<?php

namespace Tests\Feature\Sensors;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Monolog\LogRecord;
use RuntimeException;
use Tests\TestCase;

use function expect;
use function microtime;
use function now;

class LogSensorTest extends TestCase
{
    protected function setUp(): void
    {
        $this->forceRequestExecutionState();

        parent::setUp();

        $this->setDeploy('v1.2.3');
        $this->setServerName('web-01');
        $this->setPeakMemory(1234);
        $this->setTraceId('00000000-0000-0000-0000-000000000000');
        $this->setExecutionId('00000000-0000-0000-0000-000000000001');
        $this->setExecutionStart(CarbonImmutable::parse('2000-01-01 01:02:03.456789'));
    }

    public function test_it_ingests_logs()
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', function () {
            Log::channel('nightwatch')->info('hello world');
        });

        $response = $this->get('/users');

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
                'execution_preview' => 'GET /users',
                'execution_stage' => 'action',
                'user' => '',
                'level' => 'info',
                'message' => 'hello world',
                'context' => '{}',
                'extra' => '{}',
            ]);

            return true;
        });
    }

    public function test_it_formats_messages_with_replacements()
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', function () {
            Log::channel('nightwatch')->info('hello {location}', [
                'location' => 'world',
            ]);
        });

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('log:0.message', 'hello world');
    }

    public function test_it_formats_messages_with_replacement_dates_using_configured_format()
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', function () {
            Log::channel('nightwatch')->info('{datetime} - {datetimeimmutable} - {carbon} - {carbonimmutable}', [
                'datetime' => now()->toDateTime(),
                'datetimeimmutable' => now()->toDateTimeImmutable(),
                'carbon' => now(),
                'carbonimmutable' => now()->toImmutable(),
            ]);
        });

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('log:0.message', '2000-01-01 01:02:03.456789+00:00 - 2000-01-01 01:02:03.456789+00:00 - 2000-01-01 01:02:03.456789+00:00 - 2000-01-01 01:02:03.456789+00:00');
    }

    public function test_it_always_logs_ut_c_time()
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', function () {
            Log::channel('nightwatch')->info('{datetime} - {datetimeimmutable} - {carbon} - {carbonimmutable}', [
                'datetime' => now('Australia/Melbourne')->toDateTime(),
                'datetimeimmutable' => now('Australia/Melbourne')->toDateTimeImmutable(),
                'carbon' => now('Australia/Melbourne'),
                'carbonimmutable' => now('Australia/Melbourne')->toImmutable(),
            ]);
        });

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('log:0.message', '2000-01-01 01:02:03.456789+00:00 - 2000-01-01 01:02:03.456789+00:00 - 2000-01-01 01:02:03.456789+00:00 - 2000-01-01 01:02:03.456789+00:00');
    }

    public function test_it_does_not_mutate_the_date_objects()
    {
        $ingest = $this->fakeIngest();
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

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('log:0.message', '2000-01-01 01:02:03.456789+00:00 - 2000-01-01 01:02:03.456789+00:00 - 2000-01-01 01:02:03.456789+00:00 - 2000-01-01 01:02:03.456789+00:00');
        expect($datetime->getTimezone()->getName())->toBe('Australia/Melbourne');
        expect($carbon->getTimezone()->getName())->toBe('Australia/Melbourne');
        expect($datetimeImmutable->getTimezone()->getName())->toBe('Australia/Melbourne');
        expect($carbonImmutable->getTimezone()->getName())->toBe('Australia/Melbourne');
    }

    public function test_it_captures_log_context()
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', function () {
            Log::channel('nightwatch')->info('Hello world!', [
                'context' => 'value',
                'date' => now(),
            ]);
        });

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('log:0.context', '{"context":"value","date":"2000-01-01 01:02:03.456789+00:00"}');
        $ingest->assertLatestWrite('log:0.extra', '{}');
    }

    public function test_it_captures_shared_log_context()
    {
        $ingest = $this->fakeIngest();
        Log::shareContext([
            'shared' => 'context',
        ]);
        Route::get('/users', function () {
            Log::channel('nightwatch')->info('Hello world!');
        });

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('log:0.context', '{"shared":"context"}');
        $ingest->assertLatestWrite('log:0.extra', '{}');
    }

    public function test_it_captures_extra()
    {
        $ingest = $this->fakeIngest();
        Log::channel('nightwatch')->pushProcessor(fn (LogRecord $record) => $record->with(extra: [
            'extra' => 'context',
        ]));
        Route::get('/users', function () {
            Log::channel('nightwatch')->info('Hello world!');
        });

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('log:0.extra', '{"extra":"context"}');
        $ingest->assertLatestWrite('log:0.context', '{}');
    }

    public function test_it_normalizes_context()
    {
        $ingest = $this->fakeIngest();
        $e = new RuntimeException('Whoops!');
        Route::get('/', function () {
            Log::channel('nightwatch')->info('Whoops!', [
                'o' => (object) [
                    'hello' => 'world',
                ],
            ]);
        });

        $response = $this->get('/');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('log:0.context', '{"o":{"stdClass":{"hello":"world"}}}');
    }

    public function test_it_normalize_sextra()
    {
        $ingest = $this->fakeIngest();
        $e = new RuntimeException('Whoops!');
        Log::channel('nightwatch')->pushProcessor(fn (LogRecord $record) => $record->with(extra: [
            'o' => (object) [
                'hello' => 'world',
            ],
        ]));
        Route::get('/', function () {
            Log::channel('nightwatch')->info('Whoops!');
        });

        $response = $this->get('/');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('log:0.extra', '{"o":{"stdClass":{"hello":"world"}}}');
    }
}
