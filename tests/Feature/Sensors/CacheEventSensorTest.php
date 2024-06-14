<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Laravel\Nightwatch\Sensors\CacheEventSensor;

use function Pest\Laravel\post;
use function Pest\Laravel\travelTo;
use function Pest\Laravel\withoutExceptionHandling;

beforeEach(function () {
    syncClock();
    setDeployId('v1.2.3');
    setServerName('web-01');
    setPeakMemoryInKilobytes(1234);
    setTraceId('00000000-0000-0000-0000-000000000000');
    travelTo(CarbonImmutable::parse('2000-01-01 00:00:00'));
});

// TODO we might not need this if we manually create the objects.
it('lazily resolves the sensor', function () {
    expect(app()->resolved(CacheEventSensor::class))->toBeFalse();
});

it('can ingest cache misses', function () {
    withoutExceptionHandling();
    $ingest = fakeIngest();
    Route::post('/users', function () {
        Cache::driver('array')->get('users:345');
    });

    $response = post('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite([
        'requests' => [
            [
                'v' => 1,
                'timestamp' => '2000-01-01 00:00:00',
                'deploy_id' => 'v1.2.3',
                'server' => 'web-01',
                'group' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'user' => '',
                'method' => 'POST',
                'route' => '/users',
                'path' => '/users',
                'ip' => '127.0.0.1',
                'duration' => 0,
                'status_code' => '200',
                'request_size_kilobytes' => 0,
                'response_size_kilobytes' => 0,
                'queries' => 0,
                'queries_duration' => 0,
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
                'cache_misses' => 1,
                'hydrated_models' => 0,
                'peak_memory_usage_kilobytes' => 1234,
            ],
        ],
        'cache_events' => [
            [
                'v' => 1,
                'timestamp' => '2000-01-01 00:00:00',
                'deploy_id' => 'v1.2.3',
                'server' => 'web-01',
                'group' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'execution_context' => 'request',
                'execution_id' => '00000000-0000-0000-0000-000000000000',
                'user' => '',
                'store' => 'array',
                'key' => 'users:345',
                'type' => 'miss',
            ],
        ],
        'commands' => [],
        'exceptions' => [],
        'job_attempts' => [],
        'lazy_loads' => [],
        'logs' => [],
        'mail' => [],
        'notifications' => [],
        'outgoing_requests' => [],
        'queries' => [],
        'queued_jobs' => [],
    ]);
});

it('can ingest cache hits', function () {
    $ingest = fakeIngest();
    Cache::driver('array')->put('users:345', 'xxxx');
    Route::post('/users', function () {
        Cache::driver('array')->get('users:345');
    });

    $response = post('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite([
        'requests' => [
            [
                'v' => 1,
                'timestamp' => '2000-01-01 00:00:00',
                'deploy_id' => 'v1.2.3',
                'server' => 'web-01',
                'group' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'user' => '',
                'method' => 'POST',
                'route' => '/users',
                'path' => '/users',
                'ip' => '127.0.0.1',
                'duration' => 0,
                'status_code' => '200',
                'request_size_kilobytes' => 0,
                'response_size_kilobytes' => 0,
                'queries' => 0,
                'queries_duration' => 0,
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
                'cache_hits' => 1,
                'cache_misses' => 0,
                'hydrated_models' => 0,
                'peak_memory_usage_kilobytes' => 1234,
            ],
        ],
        'cache_events' => [
            [
                'v' => 1,
                'timestamp' => '2000-01-01 00:00:00',
                'deploy_id' => 'v1.2.3',
                'server' => 'web-01',
                'group' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'execution_context' => 'request',
                'execution_id' => '00000000-0000-0000-0000-000000000000',
                'user' => '',
                'store' => 'array',
                'key' => 'users:345',
                'type' => 'hit',
            ],
        ],
        'commands' => [],
        'exceptions' => [],
        'job_attempts' => [],
        'lazy_loads' => [],
        'logs' => [],
        'mail' => [],
        'notifications' => [],
        'outgoing_requests' => [],
        'queries' => [],
        'queued_jobs' => [],
    ]);
});

it('has a deploy_id fallback')->todo();
