<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\call;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    syncClock();
    setDeployId('v1.2.3');
    setServerName('web-01');
    setPeakMemoryInKilobytes(1234);
    setTraceId('00000000-0000-0000-0000-000000000000');
    travelTo(CarbonImmutable::parse('2000-01-01 00:00:00'));
});

it('can ingest requests', function () {
    $ingest = fakeIngest();
    Route::post('/users/{user}', function () {
        travelTo(now()->addMilliseconds(1234));

        return str_repeat('a', 2000);
    });

    $response = call('POST', '/users/345', content: str_repeat('b', 3000));

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
                'route' => '/users/{user}',
                'path' => '/users/345',
                'ip' => '127.0.0.1',
                'duration' => 1234,
                'status_code' => '200',
                'request_size_kilobytes' => 3,
                'response_size_kilobytes' => 2,
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
                'cache_misses' => 0,
                'hydrated_models' => 0,
                'peak_memory_usage_kilobytes' => 1234,
            ],
        ],
        'cache_events' => [],
        'commands' => [],
        'exceptions' => [],
        'outgoing_requests' => [],
        'queries' => [],
    ]);
});

it('handles requests with no content-length header, such as chunked requests')->todo();

it('handles routes with domains')->todo(); // 'path' field or dedicated column

it('handles unknown routes')->todo(); // 'route' field

it('records authenticated user')->todo(); // 'user' field

it('handles streams')->todo(); // Content-Length

it('handles 304 Not Modified responses')->todo(); // Content-Length

it('handles HEAD requests')->todo(); // Content-Length

it('handles responses using Transfer-Encoding headers')->todo(); // Content-Length

it('captures query count')->todo(); // `queries` field + for the request of the execution context.

it('has a deploy_id fallback')->todo();
