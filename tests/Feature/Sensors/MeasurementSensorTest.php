<?php

namespace Tests\Feature\Sensors;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Laravel\Nightwatch\Facades\Nightwatch;
use Tests\TestCase;

use function microtime;

class MeasurementSensorTest extends TestCase
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

    public function test_it_ingests_measurements(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', function () {
            Nightwatch::measure('geocode-address', function () {
                // Do something...
            });
        });

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.measurements', 1);
        $ingest->assertLatestWrite('measurement:*', function (array $records) {
            $this->assertCount(1, $records);
            $this->assertArrayHasKey('timestamp', $records[0]);
            $this->assertIsFloat($records[0]['timestamp']);
            $this->assertEqualsWithDelta($records[0]['timestamp'], microtime(true), 0.1);
            $this->assertSame([
                'v' => 1,
                't' => 'measurement',
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => hash('xxh128', 'geocode-address'),
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'execution_source' => 'request',
                'execution_id' => '00000000-0000-0000-0000-000000000001',
                'execution_preview' => 'GET /users',
                'execution_stage' => 'action',
                'user' => '',
                'name' => 'geocode-address',
                'file' => 'tests/Feature/Sensors/MeasurementSensorTest.php',
                'line' => 36,
            ], Arr::except($records[0], ['timestamp', 'duration']));

            $this->assertArrayHasKey('duration', $records[0]);
            $this->assertIsInt($records[0]['duration']);
            $this->assertGreaterThanOrEqual(0, $records[0]['duration']);

            return true;
        });
    }

    public function test_it_returns_callback_return_value(): void
    {
        $ingest = $this->fakeIngest();
        $result = null;
        Route::get('/users', function () use (&$result) {
            $result = Nightwatch::measure('compute-value', function () {
                return 'expected-value';
            });
        });

        $response = $this->get('/users');

        $response->assertOk();
        $this->assertSame('expected-value', $result);
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('measurement:0.name', 'compute-value');
    }

    public function test_it_captures_duration(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', function () {
            Nightwatch::measure('slow-operation', function () {
                usleep(10000); // 10ms
            });
        });

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('measurement:0', function (array $record) {
            // Duration should be at least 10000 microseconds (10ms)
            $this->assertGreaterThanOrEqual(10000, $record['duration']);

            return true;
        });
    }

    public function test_it_captures_multiple_measurements(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', function () {
            Nightwatch::measure('first-operation', fn () => null);
            Nightwatch::measure('second-operation', fn () => null);
            Nightwatch::measure('third-operation', fn () => null);
        });

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.measurements', 3);
        $ingest->assertLatestWrite('measurement:0.name', 'first-operation');
        $ingest->assertLatestWrite('measurement:1.name', 'second-operation');
        $ingest->assertLatestWrite('measurement:2.name', 'third-operation');
    }

    public function test_it_respects_pause_state(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', function () {
            Nightwatch::pause();
            Nightwatch::measure('paused-measurement', fn () => null);
            Nightwatch::resume();
            Nightwatch::measure('resumed-measurement', fn () => null);
        });

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.measurements', 1);
        $ingest->assertLatestWrite('measurement:0.name', 'resumed-measurement');
    }

    public function test_it_truncates_long_names(): void
    {
        $ingest = $this->fakeIngest();
        $longName = str_repeat('a', 300);
        Route::get('/users', function () use ($longName) {
            Nightwatch::measure($longName, fn () => null);
        });

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('measurement:0.name', function ($name) {
            $this->assertLessThanOrEqual(255, strlen($name));

            return true;
        });
    }

    public function test_it_groups_by_name(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', function () {
            Nightwatch::measure('same-name', fn () => null);
            Nightwatch::measure('same-name', fn () => null);
        });

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('measurement:0._group', hash('xxh128', 'same-name'));
        $ingest->assertLatestWrite('measurement:1._group', hash('xxh128', 'same-name'));
    }

    public function test_callback_return_value_is_passed_through_even_when_disabled(): void
    {
        $ingest = $this->fakeIngest();
        $result = null;
        Route::get('/users', function () use (&$result) {
            Nightwatch::pause();
            $result = Nightwatch::measure('disabled-measurement', function () {
                return 'still-returned';
            });
        });

        $response = $this->get('/users');

        $response->assertOk();
        $this->assertSame('still-returned', $result);
    }
}
