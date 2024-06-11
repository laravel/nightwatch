<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\post;
use function Pest\Laravel\travelTo;
use function Pest\Laravel\withoutExceptionHandling;

beforeEach(function () {
    setDeployId('v1.2.3');
    setServerName('web-01');
    setPeakMemoryInKilobytes(1234);
    setTraceId('00000000-0000-0000-0000-000000000000');
    travelTo(CarbonImmutable::parse('2000-01-01 00:00:00'));

    Http::preventStrayRequests();
});

it('lazily resolves the sensor', function () {
    expect(app()->resolved(OutgoingRequestsSensor::class))->toBeFalse();
});

it('ingests outgoing requests', function () {
    withoutExceptionHandling();
    $ingest = fakeIngest();
    Route::post('/users', function () {
        Http::withBody(str_repeat('b', 2000))->post('https://laravel.com');
    });

    Http::fake([
        'https://laravel.com' => function () {
            travelTo(now()->addMilliseconds(1234));

            return Http::response(str_repeat('a', 3000));
        },
    ]);
    $response = post('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite([
        'requests' => [
            [
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
                'duration' => 1234,
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
                'outgoing_requests' => 1,
                'outgoing_requests_duration' => 1234,
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
        'outgoing_requests' => [
            [
                'timestamp' => '2000-01-01 00:00:00',
                'deploy_id' => 'v1.2.3',
                'server' => 'web-01',
                'group' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'execution_context' => 'request',
                'execution_id' => '00000000-0000-0000-0000-000000000000',
                'user' => '',
                'method' => 'POST',
                'url' => 'https://laravel.com',
                'duration' => 1234,
                'request_size_kilobytes' => 2,
                'response_size_kilobytes' => 3,
                'status_code' => '200',
            ],
        ],
        'queries' => [],
        'queued_jobs' => [],
    ]);
});
