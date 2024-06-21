<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\File\Stream;

use function Pest\Laravel\call;
use function Pest\Laravel\get;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    setDeployId('v1.2.3');
    setServerName('web-01');
    setPeakMemoryInKilobytes(1234);
    setTraceId('00000000-0000-0000-0000-000000000000');
    syncClock(CarbonImmutable::parse('2000-01-01 00:00:00')->startOfSecond());
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
                'path' => '/users/345',
                'query' => '',
                'route_name' => '',
                'route_methods' => ['POST'],
                'route_domain' => '',
                'route_path' => '/users/{user}',
                'route_action' => 'Closure',
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

it('masks query parameters')->todo();

it('sorts the route methods')->todo();

it('handles streamed response sizes', function () {
    $ingest = fakeIngest();

    Route::get('test-route', function () {
        $file = new Stream(__FILE__);

        return response()->file($file);
    });

    get('test-route');

    $ingest->assertLatestWrite('requests.0.response_size_kilobytes', null);
});
