<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\post;
use function Pest\Laravel\travelTo;

beforeEach(function () use (&$ignore) {
    setDeployId('v1.2.3');
    setServerName('web-01');
    setPeakMemoryInKilobytes(1234);
    setTraceId('00000000-0000-0000-0000-000000000000');
    syncClock(CarbonImmutable::parse('2000-01-01 00:00:00'));

    App::setBasePath(realpath(__DIR__.'/../../../'));
});

it('can ingest queries', function () {
    $ingest = fakeIngest();
    prependListener(QueryExecuted::class, function (QueryExecuted $event) {
        if (! RefreshDatabaseState::$migrated) {
            return false;
        }

        $event->time = 5.2;

        travelTo(now()->addMilliseconds(5.2));
    });
    $line = null;
    Route::post('/users', function () use (&$line) {
        travelTo(now()->addMilliseconds(2.5));

        $line = __LINE__ + 1;
        DB::table('users')->get();
    });

    $response = post('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite([
        'requests' => [
            [
                'v' => 1,
                'timestamp' => 946684800,
                'deploy_id' => 'v1.2.3',
                'server' => 'web-01',
                'group' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'user' => '',
                'method' => 'POST',
                'scheme' => 'http',
                'url_user' => '',
                'host' => 'localhost',
                'port' => '80',
                'path' => '/users',
                'query' => '',
                'route_name' => '',
                'route_methods' => ['POST'],
                'route_domain' => '',
                'route_path' => '/users',
                'route_action' => 'Closure',
                'ip' => '127.0.0.1',
                'duration' => 8,
                'status_code' => '200',
                'request_size_kilobytes' => 0,
                'response_size_kilobytes' => 0,
                'queries' => 1,
                'queries_duration' => 5200,
                'lazy_loads' => 0,
                'lazy_loads_duration' => 0,
                'jobs_queued' => 0,
                'mail_queued' => 0,
                'mail_sent' => 0,
                'mail_duration' => 0,
                'notifications_queued' => 0,
                'notifications_sent' => 0,
                'notifications_duration' => 0,
                'outgoing_requests' => 0,
                'outgoing_requests_duration' => 0,
                'files_read' => 0,
                'files_read_duration' => 0,
                'files_written' => 0,
                'files_written_duration' => 0,
                'cache_hits' => 0,
                'cache_misses' => 0,
                'hydrated_models' => 0,
                'peak_memory_usage_kilobytes' => 1234,
            ],
        ],
        'cache_events' => [],
        'commands' => [],
        'exceptions' => [],
        'job_attempts' => [],
        'lazy_loads' => [],
        'logs' => [],
        'mail' => [],
        'notifications' => [],
        'outgoing_requests' => [],
        'queries' => [
            [
                'v' => 1,
                'timestamp' => 946684800,
                'deploy_id' => 'v1.2.3',
                'server' => 'web-01',
                'group' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'execution_context' => 'request',
                'execution_id' => '00000000-0000-0000-0000-000000000000',
                'execution_offset' => 2500,
                'user' => '',
                'sql' => 'select * from "users"',
                'category' => 'select',
                'file' => 'tests/Feature/Sensors/QuerySensorTest.php',
                'line' => $line,
                'duration' => 5200,
                'connection' => 'testing',
            ],
        ],
        'queued_jobs' => [],
    ]);
});

it('has a deploy_id fallback')->todo();
