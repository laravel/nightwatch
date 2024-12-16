<?php

use Carbon\CarbonImmutable;
use GuzzleHttp\Psr7\StreamDecoratorTrait;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Psr\Http\Message\StreamInterface;

use function Orchestra\Testbench\Pest\defineEnvironment;
use function Pest\Laravel\post;
use function Pest\Laravel\travelTo;

defineEnvironment(function () {
    forceRequestExecutionState();
});

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
            '_group' => md5('laravel.com'),
            'trace_id' => '00000000-0000-0000-0000-000000000000',
            'execution_source' => 'request',
            'execution_id' => '00000000-0000-0000-0000-000000000001',
            'execution_stage' => 'action',
            'user' => '',
            'host' => 'laravel.com',
            'method' => 'POST',
            'url' => 'https://laravel.com',
            'duration' => 1234000,
            'request_size' => 2000,
            'response_size' => 3000,
            'status_code' => 200,
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
    $ingest->assertLatestWrite('outgoing-request:0.url', 'https://laravel.com:4321');
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

it("doesn't capture the outgoing request URL authentication details", function () {
    $ingest = fakeIngest();
    Route::post('/users', function () {
        Http::withBasicAuth('ryuta', 'secret')->get('https://laravel.com');
        Http::withDigestAuth('ryuta', 'secret')->get('https://laravel.com');
        Http::get('https://ryuta:secret@laravel.com');
    });
    Http::fake([
        'https://*laravel.com' => function () {
            return Http::response('ok');
        },
    ]);

    $response = post('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('outgoing-request:0.url', 'https://laravel.com');
    $ingest->assertLatestWrite('outgoing-request:1.url', 'https://laravel.com');
    $ingest->assertLatestWrite('outgoing-request:2.url', 'https://laravel.com');
    expect($ingest->latestWriteAsString())->not->toContain('ryuta');
    expect($ingest->latestWriteAsString())->not->toContain('secret');
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
