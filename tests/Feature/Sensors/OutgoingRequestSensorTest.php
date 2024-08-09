<?php

use Carbon\CarbonImmutable;
use GuzzleHttp\Psr7\StreamDecoratorTrait;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\StreamInterface;

use function Pest\Laravel\post;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    setDeploy('v1.2.3');
    setServerName('web-01');
    setPeakMemory(1234);
    setTraceId('00000000-0000-0000-0000-000000000000');
    syncClock(CarbonImmutable::parse('2000-01-01 00:00:00.000'));

    Http::resolved(fn () => Http::preventStrayRequests());
})->skip();

it('ingests outgoing requests', function () {
    $ingest = fakeIngest();
    Route::post('/users', function () {
        travelTo(now()->addMilliseconds(2.5));

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
                'v' => 1,
                'timestamp' => 946684800,
                'deploy' => 'v1.2.3',
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
                'duration' => 1237,
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
                'v' => 1,
                'timestamp' => 946684800,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                'group' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'execution_context' => 'request',
                'execution_id' => '00000000-0000-0000-0000-000000000001',
                'execution_offset' => 2500,
                'user' => '',
                'method' => 'POST',
                'scheme' => 'https',
                'host' => 'laravel.com',
                'port' => '443',
                'path' => '',
                'route' => '',
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

it('captures the request / response size kilobytes from the content-length header', function () {
    $ingest = fakeIngest();
    Route::post('/users', function () {
        Http::withBody(new NoReadStream(null))->withHeader('Content-Length', 9876)->post('https://laravel.com');
    });
    Http::fake([
        'https://laravel.com' => function () {
            return Http::response(new NoReadStream(null), headers: ['Content-Length' => 5432]);
        },
    ]);

    $response = post('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('outgoing_requests.0.request_size_kilobytes', 10);
    $ingest->assertLatestWrite('outgoing_requests.0.response_size_kilobytes', 5);
});

it('captures the response size kilobytes from the stream if not present in the content-length header', function () {
    $ingest = fakeIngest();
    Route::post('/users', function () {
        Http::withBody(new NoReadStream(9876))->post('https://laravel.com');
    });

    Http::fake([
        'https://laravel.com' => function ($request) {
            return Http::response(new NoReadStream(5432));
        },
    ]);

    $response = post('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('outgoing_requests.0.request_size_kilobytes', 10);
    $ingest->assertLatestWrite('outgoing_requests.0.response_size_kilobytes', 5);
});

it('does not read the stream into memory to determine the size of the response', function () {
    $ingest = fakeIngest();
    Route::post('/users', function () {
        Http::withBody(new NoReadStream(null))->post('https://laravel.com');
    });

    Http::fake([
        'https://laravel.com' => function ($request) {
            return Http::response(new NoReadStream(null));
        },
    ]);

    $response = post('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('outgoing_requests.0.request_size_kilobytes', null);
    $ingest->assertLatestWrite('outgoing_requests.0.response_size_kilobytes', null);
});

it('captures the port when specified', function () {
    $ingest = fakeIngest();
    Route::post('/users', function () {
        Http::post('https://laravel.com:4321');
    });
    Http::fake([
        'https://laravel.com:4321' => function () {
            return Http::response();
        },
    ]);

    $response = post('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('outgoing_requests.0.port', '4321');
});

it('captures the default port for insecure requests when not specified', function () {
    $ingest = fakeIngest();
    Route::post('/users', function () {
        Http::post('http://laravel.com');
    });
    Http::fake([
        'http://laravel.com' => function () {
            return Http::response();
        },
    ]);

    $response = post('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('outgoing_requests.0.port', '80');
});

it('captures the default port for secure requests when not specified', function () {
    $ingest = fakeIngest();
    Route::post('/users', function () {
        Http::post('https://laravel.com');
    });
    Http::fake([
        'https://laravel.com' => function () {
            return Http::response();
        },
    ]);

    $response = post('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('outgoing_requests.0.port', '443');
});

final class NoReadStream implements StreamInterface
{
    use StreamDecoratorTrait {
        __construct as __constructParent;
    }

    public function __construct(private ?int $size)
    {
        //
    }

    public function getSize()
    {
        return $this->size;
    }

    public function read($length)
    {
        throw new RuntimeException('This stream should not be read!');
    }

    public function __toString()
    {
        throw new RuntimeException('This stream should not be read!');
    }

    public function detach()
    {
        throw new RuntimeException('This stream should not be read!');
    }

    public function seek($offset, $whence = SEEK_SET): void
    {
        throw new RuntimeException('This stream should not be read!');
    }

    public function isSeekable()
    {
        return true;
    }

    public function getContents()
    {
        throw new RuntimeException('This stream should not be read!');
    }
}
