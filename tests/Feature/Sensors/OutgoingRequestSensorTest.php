<?php

use Carbon\CarbonImmutable;
use GuzzleHttp\Psr7\StreamDecoratorTrait;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\StreamInterface;

use function Pest\Laravel\post;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    setDeploy('v1.2.3');
    setServerName('web-01');
    setPeakMemory(1234);
    setTraceId('00000000-0000-0000-0000-000000000000');
    setExecutionId('00000000-0000-0000-0000-000000000001');
    setExecutionStart(CarbonImmutable::parse('2000-01-01 01:02:03.456789'));

    Http::preventStrayRequests();
});

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
    $ingest->assertLatestWrite('request:0.outgoing_requests', 1);
    $ingest->assertLatestWrite('outgoing-request:*', [
        [
            'v' => 1,
            't' => 'outgoing-request',
            'timestamp' => 946688523.459289,
            'deploy' => 'v1.2.3',
            'server' => 'web-01',
            'group' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
            'trace_id' => '00000000-0000-0000-0000-000000000000',
            'execution_context' => 'request',
            'execution_id' => '00000000-0000-0000-0000-000000000001',
            'user' => '',
            'method' => 'POST',
            'scheme' => 'https',
            'host' => 'laravel.com',
            'port' => '443',
            'path' => '',
            'duration' => 1234000,
            'request_size' => 2000,
            'response_size' => 3000,
            'status_code' => '200',
        ],
    ]);
});

it('captures the request / response size bytes from the content-length header', function () {
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
    $ingest->assertLatestWrite('outgoing-request:0.request_size', 9876);
    $ingest->assertLatestWrite('outgoing-request:0.response_size', 5432);
});

it('captures the response size bytes from the stream if not present in the content-length header', function () {
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
    $ingest->assertLatestWrite('outgoing-request:0.request_size', 9876);
    $ingest->assertLatestWrite('outgoing-request:0.response_size', 5432);
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
    $ingest->assertLatestWrite('outgoing-request:0.request_size', 0);
    $ingest->assertLatestWrite('outgoing-request:0.response_size', 0);
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
    $ingest->assertLatestWrite('outgoing-request:0.port', '4321');
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
    $ingest->assertLatestWrite('outgoing-request:0.port', '80');
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
    $ingest->assertLatestWrite('outgoing-request:0.port', '443');
});

it('gracefully handles request / response sizes that are streams', function () {
    $ingest = fakeIngest();
    Route::post('/users', function () {
        Http::withBody(new NoReadStream(null))->post('https://laravel.com');
    });
    Http::fake([
        'https://laravel.com' => function () {
            return Http::response(new NoReadStream(null));
        },
    ]);

    $response = post('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('outgoing-request:0.request_size', 0);
    $ingest->assertLatestWrite('outgoing-request:0.response_size', 0);
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
