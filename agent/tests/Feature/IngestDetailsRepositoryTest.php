<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\BrowserFake;
use Tests\Connection;
use Tests\LoopFake;
use Tests\Request;
use Tests\Response;
use Tests\TcpServerFake;
use Tests\TestCase;
use Tests\Timer;

use function gethostname;
use function json_encode;
use function preg_quote;
use function str_repeat;
use function strlen;

class IngestDetailsRepositoryTest extends TestCase
{
    public function test_it_handles_runtime_exceptions_while_procesing_the_request(): void
    {
        $loop = new LoopFake;
        $browser = new BrowserFake([
            Response::throwWhileProcessing('Whoops!'),
        ]);

        [$output, $e] = $this->runAgent(
            via: 'source',
            ingestDetailsBrowser: $browser,
            loop: $loop,
        );

        $this->assertNull($e, $e?->getMessage() ?? '');
        $browser->assertSent([
            Request::json('/api/agent-auth'),
        ]);
        $browser->assertPending([]);
        $this->assertLogMatches(<<<'OUTPUT'
        {date} {error} Authentication failed {duration}: Whoops!
        OUTPUT, $output);
        $loop->assertRun([]);
    }

    public function test_it_handles_4xx_errors(): void
    {
        $loop = new LoopFake;
        $browser = new BrowserFake([
            new Response('Whoops!', 400),
        ]);

        [$output, $e] = $this->runAgent(
            via: 'source',
            ingestDetailsBrowser: $browser,
            loop: $loop,
        );

        $this->assertNull($e, $e?->getMessage() ?? '');
        $browser->assertSent([
            Request::json('/api/agent-auth'),
        ]);
        $browser->assertPending([]);
        $this->assertLogMatches(<<<'OUTPUT'
        {date} {error} Authentication failed {duration}: 400 \[Whoops!\]
        OUTPUT, $output);
        $loop->assertRun([]);
    }

    public function test_it_handles_5xx_errors(): void
    {
        $loop = new LoopFake;
        $browser = new BrowserFake([
            Response::internalServerError('Whoops!'),
        ]);

        [$output, $e] = $this->runAgent(
            via: 'source',
            ingestDetailsBrowser: $browser,
            loop: $loop,
        );

        $this->assertNull($e, $e?->getMessage() ?? '');
        $browser->assertSent([
            Request::json('/api/agent-auth'),
        ]);
        $browser->assertPending([]);
        $this->assertLogMatches(<<<'OUTPUT'
        {date} {error} Authentication failed {duration}: 500 \[Whoops!\]
        OUTPUT, $output);
        $loop->assertRun([]);
    }

    #[DataProvider('malformedJson')]
    public function test_it_handles_malformed_json_responses(string $body): void
    {
        $loop = new LoopFake;
        $browser = new BrowserFake([
            new Response($body),
        ]);

        [$output, $e] = $this->runAgent(
            via: 'source',
            ingestDetailsBrowser: $browser,
            loop: $loop,
        );

        $this->assertNull($e, $e?->getMessage() ?? '');
        $browser->assertSent([
            Request::json('/api/agent-auth'),
        ]);
        $browser->assertPending([]);
        $this->assertLogMatches(<<<'OUTPUT'
        {date} {error} Authentication failed {duration}: Syntax error
        OUTPUT, $output);
        $loop->assertRun([]);
    }

    /**
     * @return iterable<array{0: string}>
     */
    public static function malformedJson(): iterable
    {
        yield [''];
        yield ['['];
        yield ['{'];
    }

    /**
     * @param  array<mixed>  $payload
     */
    #[DataProvider('unexpectedResponsePayloads')]
    public function test_it_handles_unexpected_response_payloads(array $payload): void
    {
        $loop = new LoopFake;
        $browser = new BrowserFake([
            new Response($payload),
        ]);

        [$output, $e] = $this->runAgent(
            via: 'source',
            ingestDetailsBrowser: $browser,
            loop: $loop,
        );

        $this->assertNull($e, $e?->getMessage() ?? '');
        $browser->assertSent([
            Request::json('/api/agent-auth'),
        ]);
        $browser->assertPending([]);
        $payload = preg_quote(json_encode($payload, flags: JSON_THROW_ON_ERROR), '#');
        $this->assertLogMatches(<<<OUTPUT
        {date} {error} Authentication failed {duration}: Invalid authentication response \[{$payload}\]
        OUTPUT, $output);
        $loop->assertRun([]);
    }

    /**
     * @return iterable<array{0: array<mixed>}>
     */
    public static function unexpectedResponsePayloads(): iterable
    {
        yield [[]];
        yield [['token' => 'token']];
        yield [['token' => 'token', 'expires_in' => 3_600]];
        yield [['token' => 'token', 'expires_in' => '3_600', 'ingest_url' => 'https://ingest.nightwatch.laravel.com']];
        yield [['token' => 'token', 'expires_in' => '3_600', 'ingest_url' => 'https://ingest.nightwatch.laravel.com', 'refresh_in' => 1_000]];
        yield [['token' => 'token', 'expires_in' => 3_600, 'ingest_url' => 'https://ingest.nightwatch.laravel.com', 'refresh_in' => '1_000']];
    }

    public function test_it_handles_valid_responses(): void
    {
        $loop = new LoopFake;
        $browser = new BrowserFake([
            Response::jwt(),
        ]);

        [$output, $e] = $this->runAgent(
            via: 'source',
            ingestDetailsBrowser: $browser,
            loop: $loop,
        );

        /** @var ?string $baseUrl */
        $baseUrl = $_SERVER['NIGHTWATCH_BASE_URL'] ?? null;
        /** @var ?string $token */
        $token = $_SERVER['NIGHTWATCH_TOKEN'] ?? null;

        $this->assertIsString($baseUrl);
        $this->assertStringStartsWith('https://', $baseUrl);
        $this->assertIsString($token);
        $this->assertSame(44, strlen($token));
        $this->assertNull($e, $e?->getMessage() ?? '');
        $this->assertSame(10.0, $browser->timeout);
        $this->assertSame(5.0, $browser->connectionTimeout);
        $this->assertSame($baseUrl, $browser->baseUrl);
        $this->assertSame([
            'accept' => 'application/json',
            'authorization' => "Bearer {$token}",
            'content-type' => 'application/json',
            'nightwatch-server' => gethostname(),
        ], $browser->headers);
        $browser->assertSent([
            Request::json('/api/agent-auth'),
        ]);
        $browser->assertPending([]);
        $this->assertLogMatches(<<<'OUTPUT'
        {date} {info} Authentication successful {duration}
        OUTPUT, $output);
        $loop->assertRun([]);
    }

    public function test_it_refreshes_the_token_based_on_refresh_in(): void
    {
        $loop = new LoopFake(runForSeconds: 5 + 10 + 3_600 + 300 + 1);
        $browser = new BrowserFake([
            Response::jwt(refreshIn: 5),

            Response::jwt(refreshIn: 10),
            Response::internalServerError(),
            Response::jwt(),
            Response::jwt(),
        ]);

        [$output, $e] = $this->runAgent(
            via: 'source',
            ingestDetailsBrowser: $browser,
            loop: $loop,
        );

        $this->assertNull($e, $e?->getMessage() ?? '');
        $scheduleRefreshIn = 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn';
        $loop->assertRun([
            new Timer(interval: 5, runAt: 5, scheduledAt: 0, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 10, runAt: 5 + 10, scheduledAt: 5, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 5 + 10 + 300, scheduledAt: 15, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 3_600, runAt: 5 + 10 + 300 + 3_600, scheduledAt: 5 + 10 + 300, scheduledBy: $scheduleRefreshIn),
        ]);
        $loop->assertPending([
            new Timer(interval: 3_600, runAt: 5 + 10 + 300 + 3_600 + 3_600, scheduledAt: 5 + 10 + 300 + 3_600, scheduledBy: $scheduleRefreshIn),
        ]);
        $browser->assertSent([
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
        ]);
        $browser->assertPending([]);
        $this->assertLogMatches(<<<'OUTPUT'
        {date} {info} Authentication successful {duration}
        {date} {info} Authentication successful {duration}
        {date} {error} Authentication failed {duration}: 500 \[\]
        {date} {info} Authentication successful {duration}
        {date} {info} Authentication successful {duration}
        OUTPUT, $output);
    }

    public function test_it_uses_the_quick_retry_back_off_strategy_if_the_agent_has_not_yet_authenticated_and_encouters_a_runtime_exception(): void
    {
        $loop = new LoopFake(runForSeconds: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + (300 * 12) + (3_600 * 3) + 1);
        $browser = new BrowserFake([
            Response::throwWhileProcessing('Whoops 1!'), // 0s

            Response::throwWhileProcessing('Whoops 2!'), // 2.5s
            Response::throwWhileProcessing('Whoops 3!'), // 5s
            Response::throwWhileProcessing('Whoops 4!'), // 10s
            Response::throwWhileProcessing('Whoops 5!'), // 15s
            Response::throwWhileProcessing('Whoops 6!'), // 30s
            Response::throwWhileProcessing('Whoops 7!'), // 60s
            Response::throwWhileProcessing('Whoops 8!'), // 120s
            Response::throwWhileProcessing('Whoops 9!'), // 240s
            Response::throwWhileProcessing('Whoops 10!'), // 300s
            Response::throwWhileProcessing('Whoops 11!'), // 300s
            Response::throwWhileProcessing('Whoops 12!'), // 300s
            Response::throwWhileProcessing('Whoops 13!'), // 300s
            Response::throwWhileProcessing('Whoops 14!'), // 300s
            Response::throwWhileProcessing('Whoops 15!'), // 300s
            Response::throwWhileProcessing('Whoops 16!'), // 300s
            Response::throwWhileProcessing('Whoops 17!'), // 300s
            Response::throwWhileProcessing('Whoops 18!'), // 300s
            Response::throwWhileProcessing('Whoops 19!'), // 300s
            Response::throwWhileProcessing('Whoops 20!'), // 300s
            Response::throwWhileProcessing('Whoops 21!'), // 300s
            Response::throwWhileProcessing('Whoops 22!'), // 1h
            Response::throwWhileProcessing('Whoops 23!'), // 1h
            Response::throwWhileProcessing('Whoops 24!'), // 1h
        ]);

        [$output, $e] = $this->runAgent(
            via: 'source',
            ingestDetailsBrowser: $browser,
            loop: $loop,
        );

        $this->assertNull($e, $e?->getMessage() ?? '');
        $scheduleRefreshIn = 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn';
        $loop->assertRun([
            new Timer(interval: 2.5, runAt: 2.5, scheduledAt: 0, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 5, runAt: 2.5 + 5, scheduledAt: 2.5, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 10, runAt: 2.5 + 5 + 10, scheduledAt: 2.5 + 5, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 15, runAt: 2.5 + 5 + 10 + 15, scheduledAt: 2.5 + 5 + 10, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 30, runAt: 2.5 + 5 + 10 + 15 + 30, scheduledAt: 2.5 + 5 + 10 + 15, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 60, runAt: 2.5 + 5 + 10 + 15 + 30 + 60, scheduledAt: 2.5 + 5 + 10 + 15 + 30, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 120, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120, scheduledAt: 2.5 + 5 + 10 + 15 + 30 + 60, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 240, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240, scheduledAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300, scheduledAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300, scheduledAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300, scheduledAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300, scheduledAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300, scheduledAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300, scheduledAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 3_600, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600, scheduledAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 3_600, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600 + 3_600, scheduledAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 3_600, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600 + 3_600 + 3_600, scheduledAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600 + 3_600, scheduledBy: $scheduleRefreshIn),
        ]);
        $loop->assertPending([
            new Timer(interval: 3_600, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600 + 3_600 + 3_600 + 3_600, scheduledAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600 + 3_600 + 3_600, scheduledBy: $scheduleRefreshIn),
        ]);
        $browser->assertSent([
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
        ]);
        $browser->assertPending([]);
        $this->assertLogMatches(<<<'OUTPUT'
        {date} {error} Authentication failed {duration}: Whoops 1!
        {date} {error} Authentication failed {duration}: Whoops 2!
        {date} {error} Authentication failed {duration}: Whoops 3!
        {date} {error} Authentication failed {duration}: Whoops 4!
        {date} {error} Authentication failed {duration}: Whoops 5!
        {date} {error} Authentication failed {duration}: Whoops 6!
        {date} {error} Authentication failed {duration}: Whoops 7!
        {date} {error} Authentication failed {duration}: Whoops 8!
        {date} {error} Authentication failed {duration}: Whoops 9!
        {date} {error} Authentication failed {duration}: Whoops 10!
        {date} {error} Authentication failed {duration}: Whoops 11!
        {date} {error} Authentication failed {duration}: Whoops 12!
        {date} {error} Authentication failed {duration}: Whoops 13!
        {date} {error} Authentication failed {duration}: Whoops 14!
        {date} {error} Authentication failed {duration}: Whoops 15!
        {date} {error} Authentication failed {duration}: Whoops 16!
        {date} {error} Authentication failed {duration}: Whoops 17!
        {date} {error} Authentication failed {duration}: Whoops 18!
        {date} {error} Authentication failed {duration}: Whoops 19!
        {date} {error} Authentication failed {duration}: Whoops 20!
        {date} {error} Authentication failed {duration}: Whoops 21!
        {date} {error} Authentication failed {duration}: Whoops 22!
        {date} {error} Authentication failed {duration}: Whoops 23!
        {date} {error} Authentication failed {duration}: Whoops 24!
        OUTPUT, $output);
    }

    public function test_it_uses_the_quick_retry_back_off_strategy_if_the_agent_has_not_yet_authenticated_and_receives_an_unknown_error_response(): void
    {
        $loop = new LoopFake(runForSeconds: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + (300 * 12) + (3_600 * 3) + 1);
        $browser = new BrowserFake([
            Response::internalServerError('Whoops 1!'), // 0s

            new Response('Whoops 2!', 501), // 2.5s
            new Response('Whoops 3!', 400), // 5s
            new Response('Whoops 4!', 402), // 10s
            Response::internalServerError('Whoops 5!'), // 30s
            Response::internalServerError('Whoops 6!'), // 60s
            Response::internalServerError('Whoops 7!'), // 120s
            Response::internalServerError('Whoops 8!'), // 240s
            Response::internalServerError('Whoops 9!'), // 300s
            Response::internalServerError('Whoops 10!'), // 300s
            Response::internalServerError('Whoops 11!'), // 300s
            Response::internalServerError('Whoops 12!'), // 300s
            Response::internalServerError('Whoops 13!'), // 300s
            Response::internalServerError('Whoops 14!'), // 300s
            Response::internalServerError('Whoops 15!'), // 300s
            Response::internalServerError('Whoops 16!'), // 300s
            Response::internalServerError('Whoops 17!'), // 300s
            Response::internalServerError('Whoops 18!'), // 300s
            Response::internalServerError('Whoops 19!'), // 300s
            Response::internalServerError('Whoops 20!'), // 300s
            Response::internalServerError('Whoops 21!'), // 1h
            Response::internalServerError('Whoops 22!'), // 1h
            Response::internalServerError('Whoops 23!'), // 1h
            Response::internalServerError('Whoops 24!'), // 1h
        ]);

        [$output, $e] = $this->runAgent(
            via: 'source',
            ingestDetailsBrowser: $browser,
            loop: $loop,
        );

        $this->assertNull($e, $e?->getMessage() ?? '');
        $scheduleRefreshIn = 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn';
        $loop->assertRun([
            new Timer(interval: 2.5, runAt: 2.5, scheduledAt: 0, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 5, runAt: 2.5 + 5, scheduledAt: 2.5, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 10, runAt: 2.5 + 5 + 10, scheduledAt: 2.5 + 5, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 15, runAt: 2.5 + 5 + 10 + 15, scheduledAt: 2.5 + 5 + 10, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 30, runAt: 2.5 + 5 + 10 + 15 + 30, scheduledAt: 2.5 + 5 + 10 + 15, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 60, runAt: 2.5 + 5 + 10 + 15 + 30 + 60, scheduledAt: 2.5 + 5 + 10 + 15 + 30, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 120, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120, scheduledAt: 2.5 + 5 + 10 + 15 + 30 + 60, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 240, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240, scheduledAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300, scheduledAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300, scheduledAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300, scheduledAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300, scheduledAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300, scheduledAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300, scheduledAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 3_600, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600, scheduledAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 3_600, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600 + 3_600, scheduledAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 3_600, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600 + 3_600 + 3_600, scheduledAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600 + 3_600, scheduledBy: $scheduleRefreshIn),
        ]);
        $loop->assertPending([
            new Timer(interval: 3_600, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600 + 3_600 + 3_600 + 3_600, scheduledAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600 + 3_600 + 3_600, scheduledBy: $scheduleRefreshIn),
        ]);
        $browser->assertPending([]);
        $browser->assertSent([
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
        ]);
        $this->assertLogMatches(<<<'OUTPUT'
        {date} {error} Authentication failed {duration}: 500 \[Whoops 1!\]
        {date} {error} Authentication failed {duration}: 501 \[Whoops 2!\]
        {date} {error} Authentication failed {duration}: 400 \[Whoops 3!\]
        {date} {error} Authentication failed {duration}: 402 \[Whoops 4!\]
        {date} {error} Authentication failed {duration}: 500 \[Whoops 5!\]
        {date} {error} Authentication failed {duration}: 500 \[Whoops 6!\]
        {date} {error} Authentication failed {duration}: 500 \[Whoops 7!\]
        {date} {error} Authentication failed {duration}: 500 \[Whoops 8!\]
        {date} {error} Authentication failed {duration}: 500 \[Whoops 9!\]
        {date} {error} Authentication failed {duration}: 500 \[Whoops 10!\]
        {date} {error} Authentication failed {duration}: 500 \[Whoops 11!\]
        {date} {error} Authentication failed {duration}: 500 \[Whoops 12!\]
        {date} {error} Authentication failed {duration}: 500 \[Whoops 13!\]
        {date} {error} Authentication failed {duration}: 500 \[Whoops 14!\]
        {date} {error} Authentication failed {duration}: 500 \[Whoops 15!\]
        {date} {error} Authentication failed {duration}: 500 \[Whoops 16!\]
        {date} {error} Authentication failed {duration}: 500 \[Whoops 17!\]
        {date} {error} Authentication failed {duration}: 500 \[Whoops 18!\]
        {date} {error} Authentication failed {duration}: 500 \[Whoops 19!\]
        {date} {error} Authentication failed {duration}: 500 \[Whoops 20!\]
        {date} {error} Authentication failed {duration}: 500 \[Whoops 21!\]
        {date} {error} Authentication failed {duration}: 500 \[Whoops 22!\]
        {date} {error} Authentication failed {duration}: 500 \[Whoops 23!\]
        {date} {error} Authentication failed {duration}: 500 \[Whoops 24!\]
        OUTPUT, $output);
    }

    public function test_it_uses_the_slow_retry_back_off_strategy_if_the_agent_has_already_authenticated_and_encouters_a_runtime_exception(): void
    {
        $loop = new LoopFake(runForSeconds: 3_600 + (300 * 12) + (3 * 3_600) + 1);
        $browser = new BrowserFake([
            Response::jwt(),

            Response::throwWhileProcessing('Whoops 1!'), // 300s
            Response::throwWhileProcessing('Whoops 2!'), // 300s
            Response::throwWhileProcessing('Whoops 3!'), // 300s
            Response::throwWhileProcessing('Whoops 4!'), // 300s
            Response::throwWhileProcessing('Whoops 5!'), // 300s
            Response::throwWhileProcessing('Whoops 6!'), // 300s
            Response::throwWhileProcessing('Whoops 7!'), // 300s
            Response::throwWhileProcessing('Whoops 8!'), // 300s
            Response::throwWhileProcessing('Whoops 9!'), // 300s
            Response::throwWhileProcessing('Whoops 10!'), // 300s
            Response::throwWhileProcessing('Whoops 11!'), // 300s
            Response::throwWhileProcessing('Whoops 12!'), // 300s
            Response::throwWhileProcessing('Whoops 13!'), // 3_600s
            Response::throwWhileProcessing('Whoops 14!'), // 3_600s
            Response::throwWhileProcessing('Whoops 15!'), // 3_600s
            Response::throwWhileProcessing('Whoops 16!'), // 3_600s
        ]);

        [$output, $e] = $this->runAgent(
            via: 'source',
            ingestDetailsBrowser: $browser,
            loop: $loop,
        );

        $this->assertNull($e, $e?->getMessage() ?? '');
        $scheduleRefreshIn = 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn';
        $loop->assertRun([
            new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 3_600 + 300, scheduledAt: 3_600, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 3_600 + 300 + 300, scheduledAt: 3_600 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 3_600 + 300 + 300 + 300, scheduledAt: 3_600 + 300 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 3_600 + 300 + 300 + 300 + 300, scheduledAt: 3_600 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 3_600 + 300 + 300 + 300 + 300 + 300, scheduledAt: 3_600 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300, scheduledAt: 3_600 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 3_600, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600, scheduledAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 3_600, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600 + 3_600, scheduledAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 3_600, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600 + 3_600 + 3_600, scheduledAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600 + 3_600, scheduledBy: $scheduleRefreshIn),
        ]);
        $loop->assertPending([
            new Timer(interval: 3_600, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600 + 3_600 + 3_600 + 3_600, scheduledAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600 + 3_600 + 3_600, scheduledBy: $scheduleRefreshIn),
        ]);
        $browser->assertSent([
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
        ]);
        $browser->assertPending([]);
        $this->assertLogMatches(<<<'OUTPUT'
        {date} {info} Authentication successful {duration}
        {date} {error} Authentication failed {duration}: Whoops 1!
        {date} {error} Authentication failed {duration}: Whoops 2!
        {date} {error} Authentication failed {duration}: Whoops 3!
        {date} {error} Authentication failed {duration}: Whoops 4!
        {date} {error} Authentication failed {duration}: Whoops 5!
        {date} {error} Authentication failed {duration}: Whoops 6!
        {date} {error} Authentication failed {duration}: Whoops 7!
        {date} {error} Authentication failed {duration}: Whoops 8!
        {date} {error} Authentication failed {duration}: Whoops 9!
        {date} {error} Authentication failed {duration}: Whoops 10!
        {date} {error} Authentication failed {duration}: Whoops 11!
        {date} {error} Authentication failed {duration}: Whoops 12!
        {date} {error} Authentication failed {duration}: Whoops 13!
        {date} {error} Authentication failed {duration}: Whoops 14!
        {date} {error} Authentication failed {duration}: Whoops 15!
        {date} {error} Authentication failed {duration}: Whoops 16!
        OUTPUT, $output);
    }

    public function test_it_uses_the_slow_retry_back_off_strategy_if_the_agent_has_already_authenticated_and_receives_an_unknown_error_response(): void
    {
        $loop = new LoopFake(runForSeconds: 3_600 + (300 * 12) + (3 * 3_600) + 1);
        $browser = new BrowserFake([
            Response::jwt(),

            Response::internalServerError('Whoops 1!'), // 300s
            new Response('Whoops 2!', 501), // 300s
            new Response('Whoops 3!', 400), // 300s
            new Response('Whoops 4!', 402), // 300s
            Response::internalServerError('Whoops 5!'), // 300s
            Response::internalServerError('Whoops 6!'), // 300s
            Response::internalServerError('Whoops 7!'), // 300s
            Response::internalServerError('Whoops 8!'), // 300s
            Response::internalServerError('Whoops 9!'), // 300s
            Response::internalServerError('Whoops 10!'), // 300s
            Response::internalServerError('Whoops 11!'), // 300s
            Response::internalServerError('Whoops 12!'), // 300s
            Response::internalServerError('Whoops 13!'), // 3_600s
            Response::internalServerError('Whoops 14!'), // 3_600s
            Response::internalServerError('Whoops 15!'), // 3_600s
            Response::internalServerError('Whoops 16!'), // 3_600s
        ]);

        [$output, $e] = $this->runAgent(
            via: 'source',
            ingestDetailsBrowser: $browser,
            loop: $loop,
        );

        $this->assertNull($e, $e?->getMessage() ?? '');
        $scheduleRefreshIn = 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn';
        $loop->assertRun([
            new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 3_600 + 300, scheduledAt: 3_600, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 3_600 + 300 + 300, scheduledAt: 3_600 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 3_600 + 300 + 300 + 300, scheduledAt: 3_600 + 300 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 3_600 + 300 + 300 + 300 + 300, scheduledAt: 3_600 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 3_600 + 300 + 300 + 300 + 300 + 300, scheduledAt: 3_600 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300, scheduledAt: 3_600 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 300, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 3_600, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600, scheduledAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 3_600, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600 + 3_600, scheduledAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600, scheduledBy: $scheduleRefreshIn),
            new Timer(interval: 3_600, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600 + 3_600 + 3_600, scheduledAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600 + 3_600, scheduledBy: $scheduleRefreshIn),
        ]);
        $loop->assertPending([
            new Timer(interval: 3_600, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600 + 3_600 + 3_600 + 3_600, scheduledAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600 + 3_600 + 3_600, scheduledBy: $scheduleRefreshIn),
        ]);
        $browser->assertSent([
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
        ]);
        $browser->assertPending([]);
        $this->assertLogMatches(<<<'OUTPUT'
        {date} {info} Authentication successful {duration}
        {date} {error} Authentication failed {duration}: 500 \[Whoops 1!\]
        {date} {error} Authentication failed {duration}: 501 \[Whoops 2!\]
        {date} {error} Authentication failed {duration}: 400 \[Whoops 3!\]
        {date} {error} Authentication failed {duration}: 402 \[Whoops 4!\]
        {date} {error} Authentication failed {duration}: 500 \[Whoops 5!\]
        {date} {error} Authentication failed {duration}: 500 \[Whoops 6!\]
        {date} {error} Authentication failed {duration}: 500 \[Whoops 7!\]
        {date} {error} Authentication failed {duration}: 500 \[Whoops 8!\]
        {date} {error} Authentication failed {duration}: 500 \[Whoops 9!\]
        {date} {error} Authentication failed {duration}: 500 \[Whoops 10!\]
        {date} {error} Authentication failed {duration}: 500 \[Whoops 11!\]
        {date} {error} Authentication failed {duration}: 500 \[Whoops 12!\]
        {date} {error} Authentication failed {duration}: 500 \[Whoops 13!\]
        {date} {error} Authentication failed {duration}: 500 \[Whoops 14!\]
        {date} {error} Authentication failed {duration}: 500 \[Whoops 15!\]
        {date} {error} Authentication failed {duration}: 500 \[Whoops 16!\]
        OUTPUT, $output);
    }

    public function test_it_limits_response_body_included_in_logs(): void
    {
        $loop = new LoopFake(runForSeconds: 2.5 + 5);
        $browser = new BrowserFake([
            Response::internalServerError(str_repeat('a', 1005)),
            Response::internalServerError(str_repeat('a', 1006)),
        ]);

        [$output, $e] = $this->runAgent(
            via: 'source',
            ingestDetailsBrowser: $browser,
            loop: $loop,
        );

        $this->assertNull($e, $e?->getMessage() ?? '');
        $scheduledBy = 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn';
        $loop->assertRun([
            new Timer(interval: 2.5, runAt: 2.5, scheduledAt: 0, scheduledBy: $scheduledBy),
        ]);
        $loop->assertPending([
            new Timer(interval: 5, runAt: 7.5, scheduledAt: 2.5, scheduledBy: $scheduledBy),
        ]);
        $browser->assertSent([
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
        ]);
        $browser->assertPending([]);
        $firstBody = str_repeat('a', 1005);
        $secondBody = str_repeat('a', 1000);
        $this->assertLogMatches(<<<OUTPUT
        {date} {error} Authentication failed {duration}: 500 \[{$firstBody}\]
        {date} {error} Authentication failed {duration}: 500 \[{$secondBody}\[\.\.\.\]\]
        OUTPUT, $output);
    }

    public function test_it_can_control_refresh_in_via_app_response(): void
    {
        $loop = new LoopFake(runForSeconds: 100);
        $ingestDetailsBrowser = new BrowserFake([
            Response::unauthenticated(['message' => 'FIRST', 'refresh_in' => 33]),
            Response::unauthenticated(['message' => 'SECOND', 'refresh_in' => 66]),
            Response::unauthenticated(['message' => 'THIRD', 'refresh_in' => 99]),
        ]);

        [$output, $e] = $this->runAgent(
            via: 'source',
            ingestDetailsBrowser: $ingestDetailsBrowser,
            loop: $loop,
        );

        $this->assertNull($e, $e?->getMessage() ?? '');
        $this->assertLogMatches(<<<OUTPUT
            {date} {error} Authentication failed {duration}: 401 \[FIRST\]
            {date} {error} Authentication failed {duration}: 401 \[SECOND\]
            {date} {error} Authentication failed {duration}: 401 \[THIRD\]
            OUTPUT, $output);

        $loop
            ->assertRun([
                new Timer(interval: 33, runAt: 33, scheduledAt: 0, scheduledBy: $scheduledBy = 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
                new Timer(interval: 66, runAt: 33 + 66, scheduledAt: 33, scheduledBy: $scheduledBy),
            ])
            ->assertPending([
                new Timer(interval: 99, runAt: 33 + 66 + 99, scheduledAt: 33 + 66, scheduledBy: $scheduledBy),
            ])
            ->assertCanceled([]);

        $ingestDetailsBrowser
            ->assertSent([
                Request::json('/api/agent-auth'),
                Request::json('/api/agent-auth'),
                Request::json('/api/agent-auth'),
            ])
            ->assertProcessing([])
            ->assertPending([]);
    }

    public function test_it_handles_expected_json_response_being_non_object(): void
    {
        $loop = new LoopFake(runForSeconds: 2);
        $ingestDetailsBrowser = new BrowserFake([
            new Response(
                body: '"hello world"',
                status: 403,
                headers: ['Content-Type' => 'application/json']
            ),
        ]);

        [$output, $e] = $this->runAgent(
            via: 'source',
            ingestDetailsBrowser: $ingestDetailsBrowser,
            loop: $loop,
        );

        $this->assertNull($e, $e?->getMessage() ?? '');
        $this->assertLogMatches(<<<OUTPUT
            {date} {error} Authentication failed {duration}: 403 \["hello world"\]
            OUTPUT, $output);

        $loop
            ->assertRun([])
            ->assertPending([
                new Timer(interval: 2.5, runAt: 2.5, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
            ])
            ->assertCanceled([]);

        $ingestDetailsBrowser
            ->assertSent([
                Request::json('/api/agent-auth'),
            ])
            ->assertProcessing([])
            ->assertPending([]);
    }

    #[DataProvider('badAuthResponses')]
    public function test_it_handles_expected_json_response_being_non_json(Response $response, string $log): void
    {
        $loop = new LoopFake(runForSeconds: 2);
        $ingestDetailsBrowser = new BrowserFake([$response]);

        [$output, $e] = $this->runAgent(
            via: 'source',
            ingestDetailsBrowser: $ingestDetailsBrowser,
            loop: $loop,
        );

        $this->assertNull($e, $e?->getMessage() ?? '');
        $log = preg_quote($log);
        $this->assertLogMatches(<<<OUTPUT
            {date} {error} Authentication failed {duration}: {$log}
            OUTPUT, $output);

        $loop
            ->assertRun([])
            ->assertPending([
                new Timer(interval: 2.5, runAt: 2.5, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
            ])
            ->assertCanceled([]);

        $ingestDetailsBrowser
            ->assertSent([
                Request::json('/api/agent-auth'),
            ])
            ->assertProcessing([])
            ->assertPending([]);
    }

    /**
     * @return iterable<array{Response, 1: string}>
     */
    public static function badAuthResponses(): iterable
    {
        yield 'bare string' => [
            new Response('hello world', status: 403), '403 [hello world]',
        ];

        yield 'json string' => [
            new Response('"hello world"', status: 403), '403 ["hello world"]',
        ];

        yield 'json missing expected keys' => [
            new Response('[]', status: 403), '403 [[]]',
        ];

        yield 'json missing values' => [
            new Response(['refresh_in' => null, 'message' => null], status: 403), '403 [{"refresh_in":null,"message":null}]',
        ];
    }

    public function test_it_drops_token_when_app_instructs_to_stop_and_schedules_refresh(): void
    {
        $loop = new LoopFake(runForSeconds: 3_612);
        $ingestDetailsBrowser = new BrowserFake([
            Response::jwt(),
            new Response(['stop' => true, 'refresh_in' => 33, 'message' => 'Exceeded quota'], status: 403),
        ]);
        $ingestBrowser = new BrowserFake([
            Response::ingested(),
        ]);
        $server = new TcpServerFake;
        $loop->addTimer(1, $server->pendingConnection([['t' => 'request']]));
        $loop->addTimer(3_601, $server->pendingConnection([['t' => 'request']]));

        [$output, $e] = $this->runAgent(
            via: 'source',
            ingestDetailsBrowser: $ingestDetailsBrowser,
            ingestBrowser: $ingestBrowser,
            server: $server,
            loop: $loop,
        );

        $this->assertNull($e, $e?->getMessage() ?? '');
        $this->assertLogMatches(<<<OUTPUT
            {date} {info} Authentication successful {duration}
            {date} {info} Ingest successful {duration}
            {date} {error} Authentication failed {duration}: 403 \[Exceeded quota\]
            {date} {error} Ingest failed {duration}: No authentication details
            OUTPUT, $output);

        $loop
            ->assertRun([
                new Timer(interval: 1, runAt: 1, scheduledAt: 0, scheduledBy: $this->functionName()),
                new Timer(interval: 10, runAt: 11, scheduledAt: 1, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
                new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
                new Timer(interval: 3_601, runAt: 3_601, scheduledAt: 0, scheduledBy: $this->functionName()),
                new Timer(interval: 10, runAt: 3_611, scheduledAt: 3_601, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
            ])
            ->assertPending([
                new Timer(interval: 33, runAt: 3_633, scheduledAt: 3_600, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
            ])
            ->assertCanceled([]);

        $ingestDetailsBrowser
            ->assertSent([
                Request::json('/api/agent-auth'),
                Request::json('/api/agent-auth'),
            ])
            ->assertProcessing([])
            ->assertPending([]);

        $server
            ->assertOpen()
            ->assertHandled([
                Connection::ok(),
                Connection::ok(),
            ]);
    }
}
