<?php

namespace Tests\Feature\Sensors;

use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\StreamDecoratorTrait;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Laravel\Nightwatch\Facades\Nightwatch;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Tests\TestCase;

use function hash;
use function now;
use function str_repeat;

class OutgoingRequestSensorTest extends TestCase
{
    protected function setUp(): void
    {
        $this->forceRequestExecutionState();

        parent::setUp();

        $this->setDeploy('v1.2.3');
        $this->setServerName('web-01');
        $this->setPeakMemory(1234);
        $this->setTraceId('00000000-0000-0000-0000-000000000000');
        $this->setExecutionId('00000000-0000-0000-0000-000000000001');
        $this->setExecutionStart(CarbonImmutable::parse('2000-01-01 01:02:03.456789'));
    }

    public function test_it_ingests_outgoing_requests(): void
    {
        $ingest = $this->fakeIngest();
        Route::post('/users', function (): void {
            $this->travelTo(now()->addMicroseconds(2500));

            Http::withBody(str_repeat('b', 2000))->post('https://laravel.com');
        });
        Http::fake([
            'https://laravel.com' => function () {
                $this->travelTo(now()->addMilliseconds(1234));

                return Http::response(str_repeat('a', 3000));
            },
        ]);

        $response = $this->post('/users');

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
                '_group' => hash('xxh128', 'laravel.com'),
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'execution_source' => 'request',
                'execution_id' => '00000000-0000-0000-0000-000000000001',
                'execution_preview' => 'POST /users',
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
    }

    public function test_it_captures_the_request_response_size_bytes_from_the_content_length_header(): void
    {
        $ingest = $this->fakeIngest();
        Route::post('/users', function (): void {
            Http::withBody(new NoReadStream(null))->withHeader('Content-Length', 9876)->post('https://laravel.com');
        });
        Http::fake([
            'https://laravel.com' => function () {
                return Http::response(new NoReadStream(null), headers: ['Content-Length' => 5432]);
            },
        ]);

        $response = $this->post('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('outgoing-request:0.request_size', 9876);
        $ingest->assertLatestWrite('outgoing-request:0.response_size', 5432);
    }

    public function test_it_captures_the_response_size_bytes_from_the_stream_if_not_present_in_the_content_length_header(): void
    {
        $ingest = $this->fakeIngest();
        Route::post('/users', function (): void {
            Http::withBody(new NoReadStream(9876))->post('https://laravel.com');
        });

        Http::fake([
            'https://laravel.com' => function ($request) {
                return Http::response(new NoReadStream(5432));
            },
        ]);

        $response = $this->post('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('outgoing-request:0.request_size', 9876);
        $ingest->assertLatestWrite('outgoing-request:0.response_size', 5432);
    }

    public function test_it_does_not_read_the_stream_into_memory_to_determine_the_size_of_the_response(): void
    {
        $ingest = $this->fakeIngest();
        Route::post('/users', function (): void {
            Http::withBody(new NoReadStream(null))->post('https://laravel.com');
        });

        Http::fake([
            'https://laravel.com' => function ($request) {
                return Http::response(new NoReadStream(null));
            },
        ]);

        $response = $this->post('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('outgoing-request:0.request_size', 0);
        $ingest->assertLatestWrite('outgoing-request:0.response_size', 0);
    }

    public function test_it_captures_the_port_when_specified(): void
    {
        $ingest = $this->fakeIngest();
        Route::post('/users', function (): void {
            Http::post('https://laravel.com:4321');
        });
        Http::fake([
            'https://laravel.com:4321' => function () {
                return Http::response();
            },
        ]);

        $response = $this->post('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('outgoing-request:0.url', 'https://laravel.com:4321');
    }

    public function test_it_gracefully_handles_request_response_sizes_that_are_streams(): void
    {
        $ingest = $this->fakeIngest();
        Route::post('/users', function (): void {
            Http::withBody(new NoReadStream(null))->post('https://laravel.com');
        });
        Http::fake([
            'https://laravel.com' => function () {
                return Http::response(new NoReadStream(null));
            },
        ]);

        $response = $this->post('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('outgoing-request:0.request_size', 0);
        $ingest->assertLatestWrite('outgoing-request:0.response_size', 0);
    }

    public function test_it_doesnt_capture_the_outgoing_request_ur_l_authentication_details(): void
    {
        $ingest = $this->fakeIngest();
        Route::post('/users', function (): void {
            Http::withBasicAuth('ryuta', 'secret')->get('https://laravel.com');
            Http::withDigestAuth('ryuta', 'secret')->get('https://laravel.com');
            Http::get('https://ryuta:secret@laravel.com');
        });
        Http::fake([
            'https://*laravel.com' => function () {
                return Http::response('ok');
            },
        ]);

        $response = $this->post('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('outgoing-request:0.url', 'https://laravel.com');
        $ingest->assertLatestWrite('outgoing-request:1.url', 'https://laravel.com');
        $ingest->assertLatestWrite('outgoing-request:2.url', 'https://laravel.com');
        $this->assertStringNotContainsString('ryuta', $ingest->latestWriteAsString());
        $this->assertStringNotContainsString('secret', $ingest->latestWriteAsString());
    }

    public function test_it_can_use_guzzle_directly(): void
    {
        $ingest = $this->fakeIngest();
        Route::post('/users', function (): void {
            $stack = new HandlerStack;
            $stack->setHandler(new CurlHandler);
            $stack->push(Nightwatch::guzzleMiddleware());
            $client = new Client(['handler' => $stack]);
            $client->get('https://laravel.com');
        });

        $response = $this->post('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('outgoing-request:0.url', 'https://laravel.com');
    }
}

final class NoReadStream implements StreamInterface
{
    use StreamDecoratorTrait {
        __construct as __constructParent;
    }

    public function __construct(private ?int $size)
    {
        //
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function read($length): string
    {
        throw new RuntimeException('This stream should not be read!');
    }

    public function __toString()
    {
        throw new RuntimeException('This stream should not be read!');
    }

    public function detach(): void
    {
        throw new RuntimeException('This stream should not be read!');
    }

    public function seek($offset, $whence = SEEK_SET): void
    {
        throw new RuntimeException('This stream should not be read!');
    }

    public function isSeekable(): bool
    {
        return true;
    }

    public function getContents(): string
    {
        throw new RuntimeException('This stream should not be read!');
    }
}
