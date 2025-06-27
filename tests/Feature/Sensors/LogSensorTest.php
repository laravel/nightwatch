<?php

namespace Tests\Feature\Sensors;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Monolog\LogRecord;
use RuntimeException;
use Tests\TestCase;

use function hex2bin;
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

    protected function tearDown(): void
    {
        parent::tearDown();

        Env::getRepository()->clear('LOG_LEVEL');
        Env::getRepository()->clear('NIGHTWATCH_LOG_LEVEL');
    }

    public function test_it_ingests_logs(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', function (): void {
            Log::channel('nightwatch')->info('hello world');
        });

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.logs', 1);
        $ingest->assertLatestWrite('log:*', function (array $records) {
            $this->assertCount(1, $records);
            $this->assertArrayHasKey('timestamp', $records[0]);
            $this->assertIsFloat($records[0]['timestamp']);
            $this->assertEqualsWithDelta($records[0]['timestamp'], microtime(true), 0.1);
            $this->assertSame([
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
            ], Arr::except($records[0], 'timestamp'));

            return true;
        });
    }

    public function test_it_formats_messages_with_replacements(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', function (): void {
            Log::channel('nightwatch')->info('hello {location}', [
                'location' => 'world',
            ]);
        });

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('log:0.message', 'hello world');
    }

    public function test_it_formats_messages_with_replacement_dates_using_configured_format(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', function (): void {
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

    public function test_it_always_logs_ut_c_time(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', function (): void {
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

    public function test_it_does_not_mutate_the_date_objects(): void
    {
        $ingest = $this->fakeIngest();
        $datetime = now('Australia/Melbourne')->toDateTime();
        $datetimeImmutable = now('Australia/Melbourne')->toDateTimeImmutable();
        $carbon = now('Australia/Melbourne')->toMutable();
        $carbonImmutable = now('Australia/Melbourne')->toImmutable();
        Route::get('/users', function () use ($datetime, $datetimeImmutable, $carbon, $carbonImmutable): void {
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
        $this->assertSame('Australia/Melbourne', $datetime->getTimezone()->getName());
        $this->assertSame('Australia/Melbourne', $carbon->getTimezone()->getName());
        $this->assertSame('Australia/Melbourne', $datetimeImmutable->getTimezone()->getName());
        $this->assertSame('Australia/Melbourne', $carbonImmutable->getTimezone()->getName());
    }

    public function test_it_captures_log_context(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', function (): void {
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

    public function test_it_captures_shared_log_context(): void
    {
        $ingest = $this->fakeIngest();
        Log::shareContext([
            'shared' => 'context',
        ]);
        Route::get('/users', function (): void {
            Log::channel('nightwatch')->info('Hello world!');
        });

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('log:0.context', '{"shared":"context"}');
        $ingest->assertLatestWrite('log:0.extra', '{}');
    }

    public function test_it_captures_extra(): void
    {
        $ingest = $this->fakeIngest();
        Log::channel('nightwatch')->pushProcessor(fn (LogRecord $record) => $record->with(extra: [
            'extra' => 'context',
        ]));
        Route::get('/users', function (): void {
            Log::channel('nightwatch')->info('Hello world!');
        });

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('log:0.extra', '{"extra":"context"}');
        $ingest->assertLatestWrite('log:0.context', '{}');
    }

    public function test_it_normalizes_context(): void
    {
        $ingest = $this->fakeIngest();
        $e = new RuntimeException('Whoops!');
        Route::get('/', function (): void {
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

    public function test_it_normalize_sextra(): void
    {
        $ingest = $this->fakeIngest();
        $e = new RuntimeException('Whoops!');
        Log::channel('nightwatch')->pushProcessor(fn (LogRecord $record) => $record->with(extra: [
            'o' => (object) [
                'hello' => 'world',
            ],
        ]));
        Route::get('/', function (): void {
            Log::channel('nightwatch')->info('Whoops!');
        });

        $response = $this->get('/');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('log:0.extra', '{"o":{"stdClass":{"hello":"world"}}}');
    }

    public function test_it_can_capture_binary_context_and_extra()
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', function (): void {
            Log::channel('nightwatch')->pushProcessor(fn (LogRecord $record) => $record->with(extra: [
                'binary' => hex2bin('abc123'),
            ]))->info('message', [
                'binary' => hex2bin('abc123'),
            ]);
        });

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('log:0.context', '{"binary":"��#"}');
        $ingest->assertLatestWrite('log:0.extra', '{"binary":"��#"}');
    }

    public function test_it_respects_the_log_level()
    {
        Env::getRepository()->set('LOG_LEVEL', 'warning');

        $this->refreshApplication();
        parent::setUp();

        $ingest = $this->fakeIngest();

        Route::get('/users', function (): void {
            Log::channel('nightwatch')->debug('hello world');
            Log::channel('nightwatch')->info('hello world');
            Log::channel('nightwatch')->notice('hello world');
            Log::channel('nightwatch')->warning('hello world');
            Log::channel('nightwatch')->error('hello world');
            Log::channel('nightwatch')->critical('hello world');
            Log::channel('nightwatch')->alert('hello world');
            Log::channel('nightwatch')->emergency('hello world');
        });

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.logs', 5);
        $ingest->assertLatestWrite('log:0.level', 'warning');
        $ingest->assertLatestWrite('log:1.level', 'error');
        $ingest->assertLatestWrite('log:2.level', 'critical');
        $ingest->assertLatestWrite('log:3.level', 'alert');
        $ingest->assertLatestWrite('log:4.level', 'emergency');
    }

    public function test_it_respects_the_nightwatch_log_level()
    {
        Env::getRepository()->set('LOG_LEVEL', 'debug');
        Env::getRepository()->set('NIGHTWATCH_LOG_LEVEL', 'warning');

        $this->refreshApplication();
        parent::setUp();

        $ingest = $this->fakeIngest();

        Route::get('/users', function (): void {
            Log::channel('nightwatch')->debug('hello world');
            Log::channel('nightwatch')->info('hello world');
            Log::channel('nightwatch')->notice('hello world');
            Log::channel('nightwatch')->warning('hello world');
            Log::channel('nightwatch')->error('hello world');
            Log::channel('nightwatch')->critical('hello world');
            Log::channel('nightwatch')->alert('hello world');
            Log::channel('nightwatch')->emergency('hello world');
        });

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.logs', 5);
        $ingest->assertLatestWrite('log:0.level', 'warning');
        $ingest->assertLatestWrite('log:1.level', 'error');
        $ingest->assertLatestWrite('log:2.level', 'critical');
        $ingest->assertLatestWrite('log:3.level', 'alert');
        $ingest->assertLatestWrite('log:4.level', 'emergency');
    }
}
