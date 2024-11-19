<?php

use Carbon\CarbonImmutable;
use Illuminate\Cache\ArrayStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\post;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    setDeploy('v1.2.3');
    setServerName('web-01');
    setPeakMemory(1234);
    setTraceId('00000000-0000-0000-0000-000000000000');
    setExecutionId('00000000-0000-0000-0000-000000000001');
    setExecutionStart(CarbonImmutable::parse('2000-01-01 01:02:03.456789'));
});

it('can ingest cache misses', function () {
    $ingest = fakeIngest();
    Route::post('/users', function () {
        travelTo(now()->addMicroseconds(2500));

        Cache::driver('array')->get('users:345');
    });

    $response = post('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite([
        'requests' => [
            [
                'v' => 1,
                'timestamp' => 946688523.456789,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => hash('md5', 'POST,,/users'),
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'user' => '',
                'method' => 'POST',
                'scheme' => 'http',
                'url_user' => '',
                'host' => 'localhost',
                'port' => 80,
                'path' => '/users',
                'query' => '',
                'route_name' => '',
                'route_methods' => ['POST'],
                'route_domain' => '',
                'route_path' => '/users',
                'route_action' => 'Closure',
                'ip' => '127.0.0.1',
                'duration' => 2500,
                'status_code' => 200,
                'request_size' => 0,
                'response_size' => 0,
                'bootstrap' => 0,
                'before_middleware' => 0,
                'action' => 2500,
                'render' => 0,
                'after_middleware' => 0,
                'sending' => 0,
                'terminating' => 0,
                'exceptions' => 0,
                'queries' => 0,
                'lazy_loads' => 0,
                'jobs_queued' => 0,
                'mail_queued' => 0,
                'mail_sent' => 0,
                'notifications_queued' => 0,
                'notifications_sent' => 0,
                'outgoing_requests' => 0,
                'files_read' => 0,
                'files_written' => 0,
                'cache_hits' => 0,
                'cache_misses' => 1,
                'cache_writes' => 0,
                'hydrated_models' => 0,
                'peak_memory_usage' => 1234,
            ],
        ],
        'cache_events' => [
            [
                'v' => 1,
                'timestamp' => 946688523.459289,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => hash('md5', 'array,users:345'),
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'execution_context' => 'request',
                'execution_id' => '00000000-0000-0000-0000-000000000001',
                'execution_stage' => 'action',
                'user' => '',
                'store' => 'array',
                'key' => 'users:345',
                'type' => 'miss',
                'duration' => 0,
                'ttl' => 0,
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
        travelTo(now()->addMicroseconds(2500));

        Cache::driver('array')->get('users:345');
    });

    $response = post('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite([
        'requests' => [
            [
                'v' => 1,
                'timestamp' => 946688523.456789,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => hash('md5', 'POST,,/users'),
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'user' => '',
                'method' => 'POST',
                'scheme' => 'http',
                'url_user' => '',
                'host' => 'localhost',
                'port' => 80,
                'path' => '/users',
                'query' => '',
                'route_name' => '',
                'route_methods' => ['POST'],
                'route_domain' => '',
                'route_path' => '/users',
                'route_action' => 'Closure',
                'ip' => '127.0.0.1',
                'duration' => 2500,
                'status_code' => 200,
                'request_size' => 0,
                'response_size' => 0,
                'bootstrap' => 0,
                'before_middleware' => 0,
                'action' => 2500,
                'render' => 0,
                'after_middleware' => 0,
                'sending' => 0,
                'terminating' => 0,
                'exceptions' => 0,
                'queries' => 0,
                'lazy_loads' => 0,
                'jobs_queued' => 0,
                'mail_queued' => 0,
                'mail_sent' => 0,
                'notifications_queued' => 0,
                'notifications_sent' => 0,
                'outgoing_requests' => 0,
                'files_read' => 0,
                'files_written' => 0,
                'cache_hits' => 1,
                'cache_misses' => 0,
                'cache_writes' => 1,
                'hydrated_models' => 0,
                'peak_memory_usage' => 1234,
            ],
        ],
        'cache_events' => [
            [
                'v' => 1,
                'timestamp' => 946688523.456789,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => hash('md5', 'array,users:345'),
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'execution_context' => 'request',
                'execution_id' => '00000000-0000-0000-0000-000000000001',
                'execution_stage' => 'before_middleware',
                'user' => '',
                'store' => 'array',
                'key' => 'users:345',
                'type' => 'write',
                'duration' => 0,
                'ttl' => null,
            ],
            [
                'v' => 1,
                'timestamp' => 946688523.459289,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => hash('md5', 'array,users:345'),
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'execution_context' => 'request',
                'execution_id' => '00000000-0000-0000-0000-000000000001',
                'execution_stage' => 'action',
                'user' => '',
                'store' => 'array',
                'key' => 'users:345',
                'type' => 'hit',
                'duration' => 0,
                'ttl' => 0,
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

it('can ingest cache writes', function () {
    $ingest = fakeIngest();
    Route::post('/users', function () {
        travelTo(now()->addMicroseconds(2500));

        Cache::driver('array')->put('users:345', 'xxxx', 60);
    });

    $response = post('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite([
        'requests' => [
            [
                'v' => 1,
                'timestamp' => 946688523.456789,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => hash('md5', 'POST,,/users'),
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'user' => '',
                'method' => 'POST',
                'scheme' => 'http',
                'url_user' => '',
                'host' => 'localhost',
                'port' => 80,
                'path' => '/users',
                'query' => '',
                'route_name' => '',
                'route_methods' => ['POST'],
                'route_domain' => '',
                'route_path' => '/users',
                'route_action' => 'Closure',
                'ip' => '127.0.0.1',
                'duration' => 2500,
                'status_code' => 200,
                'request_size' => 0,
                'response_size' => 0,
                'bootstrap' => 0,
                'before_middleware' => 0,
                'action' => 2500,
                'render' => 0,
                'after_middleware' => 0,
                'sending' => 0,
                'terminating' => 0,
                'exceptions' => 0,
                'queries' => 0,
                'lazy_loads' => 0,
                'jobs_queued' => 0,
                'mail_queued' => 0,
                'mail_sent' => 0,
                'notifications_queued' => 0,
                'notifications_sent' => 0,
                'outgoing_requests' => 0,
                'files_read' => 0,
                'files_written' => 0,
                'cache_hits' => 0,
                'cache_misses' => 0,
                'cache_writes' => 1,
                'hydrated_models' => 0,
                'peak_memory_usage' => 1234,
            ],
        ],
        'cache_events' => [
            [
                'v' => 1,
                'timestamp' => 946688523.459289,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => hash('md5', 'array,users:345'),
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'execution_context' => 'request',
                'execution_id' => '00000000-0000-0000-0000-000000000001',
                'execution_stage' => 'action',
                'user' => '',
                'store' => 'array',
                'key' => 'users:345',
                'type' => 'write',
                'duration' => 0,
                'ttl' => 60,
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

it('handles cache drivers with no store configured', function () {
    $ingest = fakeIngest();
    Route::post('/users', function () {
        Cache::repository(new ArrayStore)->get('users:345');
    });

    $response = post('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('cache_events.0.store', '');
});
