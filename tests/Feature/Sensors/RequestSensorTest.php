<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;
use Laravel\Nightwatch\Contracts\PeakMemoryProvider;
use Laravel\Nightwatch\RecordCollection;

use function Pest\Laravel\postJson;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    App::instance(PeakMemoryProvider::class, new class implements PeakMemoryProvider
    {
        public function inKilobytes(): int
        {
            return 123456;
        }
    });
});

it('returns a request record', function () {
    /** @var RecordCollection */
    $records = App::make(RecordCollection::class);

    travelTo(Carbon::parse('2000-01-01 01:02:03'));
    Route::post('/users/{user}', function () {
        travelTo(now()->addSecond()->addMilliseconds(234));

        return 'OK';
    });

    $response = postJson('/users/345', [
        'foo' => 'bar',
    ]);

    $response->assertOk();
    expect($records['requests'])->toHaveCount(1);
    expect($records['requests'][0])->toBe([
        'timestamp' => '2000-01-01 01:02:03',
        // 'deploy_id' => '',
        // 'server' => '',
        // 'group' => '',
        // 'trace_id' => '',
        'method' => 'POST',
        'route' => '/users/{user}',
        'path' => '/users/345',
        'user' => '',
        'ip' => '127.0.0.1',
        'duration' => 1234,
        'status_code' => '200',
        'request_size_kilobytes' => 13,
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
        'peak_memory_usage_kilobytes' => 123456,
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
