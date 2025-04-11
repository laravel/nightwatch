<?php

use Tests\BrowserFake;
use Tests\LoopFake;
use Tests\Request;
use Tests\Response;
use Tests\Timer;

it('handles runtime exceptions while procesing the request', function () {
    $loop = new LoopFake;
    $browser = new BrowserFake([
        Response::throwWhileProcessing('Whoops!'),
    ]);

    [$output, $e] = run(
        via: 'source',
        ingestDetailsBrowser: $browser,
        loop: $loop,
    );

    expect($e)->toBeNull($e?->getMessage() ?? '');
    expect($browser)->toHaveSent([
        Request::json('/api/agent-auth'),
    ]);
    expect($browser)->toHavePending([]);
    expect($output)->toMatchLog(<<<'OUTPUT'
        {date} {info} Authentication failed {duration}: Whoops!
        OUTPUT);
    expect($loop)->toHaveRun([]);
});

it('handles 4xx errors', function () {
    $loop = new LoopFake;
    $browser = new BrowserFake([
        new Response('Whoops!', 400),
    ]);

    [$output, $e] = run(
        via: 'source',
        ingestDetailsBrowser: $browser,
        loop: $loop,
    );

    expect($e)->toBeNull($e?->getMessage() ?? '');
    expect($browser)->toHaveSent([
        Request::json('/api/agent-auth'),
    ]);
    expect($browser)->toHavePending([]);
    expect($output)->toMatchLog(<<<'OUTPUT'
        {date} {info} Authentication failed {duration}: 400 \[Whoops!\]
        OUTPUT);
    expect($loop)->toHaveRun([]);
});

it('handles 5xx errors', function () {
    $loop = new LoopFake;
    $browser = new BrowserFake([
        Response::internalServerError('Whoops!'),
    ]);

    [$output, $e] = run(
        via: 'source',
        ingestDetailsBrowser: $browser,
        loop: $loop,
    );

    expect($e)->toBeNull($e?->getMessage() ?? '');
    expect($browser)->toHaveSent([
        Request::json('/api/agent-auth'),
    ]);
    expect($browser)->toHavePending([]);
    expect($output)->toMatchLog(<<<'OUTPUT'
        {date} {info} Authentication failed {duration}: 500 \[Whoops!\]
        OUTPUT);
    expect($loop)->toHaveRun([]);
});

it('handles malformed JSON responses', function (string $body) {
    $loop = new LoopFake;
    $browser = new BrowserFake([
        new Response($body),
    ]);

    [$output, $e] = run(
        via: 'source',
        ingestDetailsBrowser: $browser,
        loop: $loop,
    );

    expect($e)->toBeNull($e?->getMessage() ?? '');
    expect($browser)->toHaveSent([
        Request::json('/api/agent-auth'),
    ]);
    expect($browser)->toHavePending([]);
    expect($output)->toMatchLog(<<<'OUTPUT'
        {date} {info} Authentication failed {duration}: Syntax error
        OUTPUT);
    expect($loop)->toHaveRun([]);
})->with(['', '[']);

it('handles unexpected response payloads', function (array $payload) {
    $loop = new LoopFake;
    $browser = new BrowserFake([
        new Response($payload),
    ]);

    [$output, $e] = run(
        via: 'source',
        ingestDetailsBrowser: $browser,
        loop: $loop,
    );

    expect($e)->toBeNull($e?->getMessage() ?? '');
    expect($browser)->toHaveSent([
        Request::json('/api/agent-auth'),
    ]);
    expect($browser)->toHavePending([]);
    $payload = preg_quote(json_encode($payload, flags: JSON_THROW_ON_ERROR), '#');
    expect($output)->toMatchLog(<<<OUTPUT
        {date} {info} Authentication failed {duration}: Invalid authentication response \[{$payload}\]
        OUTPUT);
    expect($loop)->toHaveRun([]);
})->with([
    [[]],
    [['token' => 'token']],
    [['token' => 'token', 'expires_in' => 3_600]],
    [['token' => 'token', 'expires_in' => '3_600', 'ingest_url' => 'https://ingest.nightwatch.laravel.com']],
    [['token' => 'token', 'expires_in' => '3_600', 'ingest_url' => 'https://ingest.nightwatch.laravel.com', 'refresh_in' => 1_000]],
    [['token' => 'token', 'expires_in' => 3_600, 'ingest_url' => 'https://ingest.nightwatch.laravel.com', 'refresh_in' => '1_000']],
]);

it('handles valid responses', function () {
    $loop = new LoopFake;
    $browser = new BrowserFake([
        Response::jwt(),
    ]);

    [$output, $e] = run(
        via: 'source',
        ingestDetailsBrowser: $browser,
        loop: $loop,
    );

    /** @var ?string $baseUrl */
    $baseUrl = $_SERVER['NIGHTWATCH_BASE_URL'] ?? null;
    /** @var ?string $token */
    $token = $_SERVER['NIGHTWATCH_TOKEN'] ?? null;

    expect($baseUrl)
        ->toBeString()
        ->toStartWith('https://');
    expect($token)
        ->toBeString()
        ->toHaveLength(44);
    expect($e)->toBeNull($e?->getMessage() ?? '');
    expect($browser->timeout)->toBe(10.0);
    expect($browser->connectionTimeout)->toBe(5.0);
    expect($browser->baseUrl)->toBe($baseUrl);
    expect($browser->headers)->toBe([
        'accept' => 'application/json',
        'authorization' => "Bearer {$token}",
        'content-type' => 'application/json',
        'nightwatch-server' => gethostname(),
    ]);
    expect($browser)->toHaveSent([
        Request::json('/api/agent-auth'),
    ]);
    expect($browser)->toHavePending([]);
    expect($output)->toMatchLog(<<<'OUTPUT'
        {date} {info} Authentication successful {duration}
        OUTPUT);
    expect($loop)->toHaveRun([]);
});

it('refreshes the token based on refresh_in', function () {
    $loop = new LoopFake(runForSeconds: 5 + 10 + 3_600 + 300 + 1);
    $browser = new BrowserFake([
        Response::jwt(refreshIn: 5),

        Response::jwt(refreshIn: 10),
        Response::internalServerError(),
        Response::jwt(),
        Response::jwt(),
    ]);

    [$output, $e] = run(
        via: 'source',
        ingestDetailsBrowser: $browser,
        loop: $loop,
    );

    expect($e)->toBeNull($e?->getMessage() ?? '');
    $scheduleRefreshIn = 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn';
    expect($loop)->toHaveRun([
        new Timer(interval: 5, runAt: 5, scheduledAt: 0, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 10, runAt: 5 + 10, scheduledAt: 5, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 5 + 10 + 300, scheduledAt: 15, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 3_600, runAt: 5 + 10 + 300 + 3_600, scheduledAt: 5 + 10 + 300, scheduledBy: $scheduleRefreshIn),
    ]);
    expect($loop)->toHavePending([
        new Timer(interval: 3_600, runAt: 5 + 10 + 300 + 3_600 + 3_600, scheduledAt: 5 + 10 + 300 + 3_600, scheduledBy: $scheduleRefreshIn),
    ]);
    expect($browser)->toHaveSent([
        Request::json('/api/agent-auth'),
        Request::json('/api/agent-auth'),
        Request::json('/api/agent-auth'),
        Request::json('/api/agent-auth'),
        Request::json('/api/agent-auth'),
    ]);
    expect($browser)->toHavePending([]);
    expect($output)->toMatchLog(<<<'OUTPUT'
        {date} {info} Authentication successful {duration}
        {date} {info} Authentication successful {duration}
        {date} {info} Authentication failed {duration}: 500 \[\]
        {date} {info} Authentication successful {duration}
        {date} {info} Authentication successful {duration}
        OUTPUT);
});

it('uses the quick-retry back-off strategy if the agent has not yet authenticated and encouters a runtime exception', function () {
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

    [$output, $e] = run(
        via: 'source',
        ingestDetailsBrowser: $browser,
        loop: $loop,
    );

    expect($e)->toBeNull($e?->getMessage() ?? '');
    $scheduleRefreshIn = 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn';
    expect($loop)->toHaveRun([
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
    expect($loop)->toHavePending([
        new Timer(interval: 3_600, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600 + 3_600 + 3_600 + 3_600, scheduledAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600 + 3_600 + 3_600, scheduledBy: $scheduleRefreshIn),
    ]);
    expect($browser)->toHaveSent([
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
    expect($browser)->toHavePending([]);
    expect($output)->toMatchLog(<<<'OUTPUT'
        {date} {info} Authentication failed {duration}: Whoops 1!
        {date} {info} Authentication failed {duration}: Whoops 2!
        {date} {info} Authentication failed {duration}: Whoops 3!
        {date} {info} Authentication failed {duration}: Whoops 4!
        {date} {info} Authentication failed {duration}: Whoops 5!
        {date} {info} Authentication failed {duration}: Whoops 6!
        {date} {info} Authentication failed {duration}: Whoops 7!
        {date} {info} Authentication failed {duration}: Whoops 8!
        {date} {info} Authentication failed {duration}: Whoops 9!
        {date} {info} Authentication failed {duration}: Whoops 10!
        {date} {info} Authentication failed {duration}: Whoops 11!
        {date} {info} Authentication failed {duration}: Whoops 12!
        {date} {info} Authentication failed {duration}: Whoops 13!
        {date} {info} Authentication failed {duration}: Whoops 14!
        {date} {info} Authentication failed {duration}: Whoops 15!
        {date} {info} Authentication failed {duration}: Whoops 16!
        {date} {info} Authentication failed {duration}: Whoops 17!
        {date} {info} Authentication failed {duration}: Whoops 18!
        {date} {info} Authentication failed {duration}: Whoops 19!
        {date} {info} Authentication failed {duration}: Whoops 20!
        {date} {info} Authentication failed {duration}: Whoops 21!
        {date} {info} Authentication failed {duration}: Whoops 22!
        {date} {info} Authentication failed {duration}: Whoops 23!
        {date} {info} Authentication failed {duration}: Whoops 24!
        OUTPUT);
});

it('uses the quick-retry back-off strategy if the agent has not yet authenticated and receives an unknown error response', function () {
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

    [$output, $e] = run(
        via: 'source',
        ingestDetailsBrowser: $browser,
        loop: $loop,
    );

    expect($e)->toBeNull($e?->getMessage() ?? '');
    $scheduleRefreshIn = 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn';
    expect($loop)->toHaveRun([
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
    expect($loop)->toHavePending([
        new Timer(interval: 3_600, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600 + 3_600 + 3_600 + 3_600, scheduledAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600 + 3_600 + 3_600, scheduledBy: $scheduleRefreshIn),
    ]);
    expect($browser)->toHavePending([]);
    expect($browser)->toHaveSent([
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
    expect($output)->toMatchLog(<<<'OUTPUT'
        {date} {info} Authentication failed {duration}: 500 \[Whoops 1!\]
        {date} {info} Authentication failed {duration}: 501 \[Whoops 2!\]
        {date} {info} Authentication failed {duration}: 400 \[Whoops 3!\]
        {date} {info} Authentication failed {duration}: 402 \[Whoops 4!\]
        {date} {info} Authentication failed {duration}: 500 \[Whoops 5!\]
        {date} {info} Authentication failed {duration}: 500 \[Whoops 6!\]
        {date} {info} Authentication failed {duration}: 500 \[Whoops 7!\]
        {date} {info} Authentication failed {duration}: 500 \[Whoops 8!\]
        {date} {info} Authentication failed {duration}: 500 \[Whoops 9!\]
        {date} {info} Authentication failed {duration}: 500 \[Whoops 10!\]
        {date} {info} Authentication failed {duration}: 500 \[Whoops 11!\]
        {date} {info} Authentication failed {duration}: 500 \[Whoops 12!\]
        {date} {info} Authentication failed {duration}: 500 \[Whoops 13!\]
        {date} {info} Authentication failed {duration}: 500 \[Whoops 14!\]
        {date} {info} Authentication failed {duration}: 500 \[Whoops 15!\]
        {date} {info} Authentication failed {duration}: 500 \[Whoops 16!\]
        {date} {info} Authentication failed {duration}: 500 \[Whoops 17!\]
        {date} {info} Authentication failed {duration}: 500 \[Whoops 18!\]
        {date} {info} Authentication failed {duration}: 500 \[Whoops 19!\]
        {date} {info} Authentication failed {duration}: 500 \[Whoops 20!\]
        {date} {info} Authentication failed {duration}: 500 \[Whoops 21!\]
        {date} {info} Authentication failed {duration}: 500 \[Whoops 22!\]
        {date} {info} Authentication failed {duration}: 500 \[Whoops 23!\]
        {date} {info} Authentication failed {duration}: 500 \[Whoops 24!\]
        OUTPUT);
});

it('schedules a refresh after 1 hour if the agent has not yet authenticated and receives an unauthenticated response', function () {
    $loop = new LoopFake(runForSeconds: (3_600 * 3) + 1);
    $browser = new BrowserFake([
        Response::unauthenticated('Missing token'),

        Response::unauthenticated('Missing token'),
        Response::unauthenticated('Invalid environment token'),
        Response::unauthenticated('Invalid environment token'),
    ]);

    [$output, $e] = run(
        via: 'source',
        ingestDetailsBrowser: $browser,
        loop: $loop,
    );

    expect($e)->toBeNull($e?->getMessage() ?? '');
    $scheduleRefreshIn = 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn';
    expect($loop)->toHaveRun([
        new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 3_600, runAt: 3_600 + 3_600, scheduledAt: 3_600, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 3_600, runAt: 3_600 + 3_600 + 3_600, scheduledAt: 3_600 + 3_600, scheduledBy: $scheduleRefreshIn),
    ]);
    expect($loop)->toHavePending([
        new Timer(interval: 3_600, runAt: 3_600 + 3_600 + 3_600 + 3_600, scheduledAt: 3_600 + 3_600 + 3_600, scheduledBy: $scheduleRefreshIn),
    ]);
    expect($browser)->toHaveSent([
        Request::json('/api/agent-auth'),
        Request::json('/api/agent-auth'),
        Request::json('/api/agent-auth'),
        Request::json('/api/agent-auth'),
    ]);
    expect($browser)->toHavePending([]);
    expect($output)->toMatchLog(<<<'OUTPUT'
        {date} {info} Authentication failed {duration}: 401 \[{"message":"Missing token"}\]
        {date} {info} Authentication failed {duration}: 401 \[{"message":"Missing token"}\]
        {date} {info} Authentication failed {duration}: 401 \[{"message":"Invalid environment token"}\]
        {date} {info} Authentication failed {duration}: 401 \[{"message":"Invalid environment token"}\]
        OUTPUT);
});

it('uses the slow-retry back-off strategy if the agent has already authenticated and encouters a runtime exception', function () {
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

    [$output, $e] = run(
        via: 'source',
        ingestDetailsBrowser: $browser,
        loop: $loop,
    );

    expect($e)->toBeNull($e?->getMessage() ?? '');
    $scheduleRefreshIn = 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn';
    expect($loop)->toHaveRun([
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
    expect($loop)->toHavePending([
        new Timer(interval: 3_600, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600 + 3_600 + 3_600 + 3_600, scheduledAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600 + 3_600 + 3_600, scheduledBy: $scheduleRefreshIn),
    ]);
    expect($browser)->toHaveSent([
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
    expect($browser)->toHavePending([]);
    expect($output)->toMatchLog(<<<'OUTPUT'
        {date} {info} Authentication successful {duration}
        {date} {info} Authentication failed {duration}: Whoops 1!
        {date} {info} Authentication failed {duration}: Whoops 2!
        {date} {info} Authentication failed {duration}: Whoops 3!
        {date} {info} Authentication failed {duration}: Whoops 4!
        {date} {info} Authentication failed {duration}: Whoops 5!
        {date} {info} Authentication failed {duration}: Whoops 6!
        {date} {info} Authentication failed {duration}: Whoops 7!
        {date} {info} Authentication failed {duration}: Whoops 8!
        {date} {info} Authentication failed {duration}: Whoops 9!
        {date} {info} Authentication failed {duration}: Whoops 10!
        {date} {info} Authentication failed {duration}: Whoops 11!
        {date} {info} Authentication failed {duration}: Whoops 12!
        {date} {info} Authentication failed {duration}: Whoops 13!
        {date} {info} Authentication failed {duration}: Whoops 14!
        {date} {info} Authentication failed {duration}: Whoops 15!
        {date} {info} Authentication failed {duration}: Whoops 16!
        OUTPUT);
});

it('uses the slow-retry back-off strategy if the agent has already authenticated and receives an unknown error response', function () {
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

    [$output, $e] = run(
        via: 'source',
        ingestDetailsBrowser: $browser,
        loop: $loop,
    );

    expect($e)->toBeNull($e?->getMessage() ?? '');
    $scheduleRefreshIn = 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn';
    expect($loop)->toHaveRun([
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
    expect($loop)->toHavePending([
        new Timer(interval: 3_600, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600 + 3_600 + 3_600 + 3_600, scheduledAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600 + 3_600 + 3_600, scheduledBy: $scheduleRefreshIn),
    ]);
    expect($browser)->toHaveSent([
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
    expect($browser)->toHavePending([]);
    expect($output)->toMatchLog(<<<'OUTPUT'
        {date} {info} Authentication successful {duration}
        {date} {info} Authentication failed {duration}: 500 \[Whoops 1!\]
        {date} {info} Authentication failed {duration}: 501 \[Whoops 2!\]
        {date} {info} Authentication failed {duration}: 400 \[Whoops 3!\]
        {date} {info} Authentication failed {duration}: 402 \[Whoops 4!\]
        {date} {info} Authentication failed {duration}: 500 \[Whoops 5!\]
        {date} {info} Authentication failed {duration}: 500 \[Whoops 6!\]
        {date} {info} Authentication failed {duration}: 500 \[Whoops 7!\]
        {date} {info} Authentication failed {duration}: 500 \[Whoops 8!\]
        {date} {info} Authentication failed {duration}: 500 \[Whoops 9!\]
        {date} {info} Authentication failed {duration}: 500 \[Whoops 10!\]
        {date} {info} Authentication failed {duration}: 500 \[Whoops 11!\]
        {date} {info} Authentication failed {duration}: 500 \[Whoops 12!\]
        {date} {info} Authentication failed {duration}: 500 \[Whoops 13!\]
        {date} {info} Authentication failed {duration}: 500 \[Whoops 14!\]
        {date} {info} Authentication failed {duration}: 500 \[Whoops 15!\]
        {date} {info} Authentication failed {duration}: 500 \[Whoops 16!\]
        OUTPUT);
});

it('schedules a refresh after 1 hour if the agent has authenticated and receives an unauthenticated response', function () {
    $loop = new LoopFake(runForSeconds: (3_600 * 4) + 1);
    $browser = new BrowserFake([
        Response::jwt(),

        Response::unauthenticated('Missing token'),
        Response::unauthenticated('Missing token'),
        Response::unauthenticated('Invalid environment token'),
        Response::unauthenticated('Invalid environment token'),
    ]);

    [$output, $e] = run(
        via: 'source',
        ingestDetailsBrowser: $browser,
        loop: $loop,
    );

    expect($e)->toBeNull($e?->getMessage() ?? '');
    $scheduleRefreshIn = 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn';
    expect($loop)->toHaveRun([
        new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 3_600, runAt: 3_600 + 3_600, scheduledAt: 3_600, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 3_600, runAt: 3_600 + 3_600 + 3_600, scheduledAt: 3_600 + 3_600, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 3_600, runAt: 3_600 + 3_600 + 3_600 + 3_600, scheduledAt: 3_600 + 3_600 + 3_600, scheduledBy: $scheduleRefreshIn),
    ]);
    expect($loop)->toHavePending([
        new Timer(interval: 3_600, runAt: 3_600 + 3_600 + 3_600 + 3_600 + 3_600, scheduledAt: 3_600 + 3_600 + 3_600 + 3_600, scheduledBy: $scheduleRefreshIn),
    ]);
    expect($browser)->toHaveSent([
        Request::json('/api/agent-auth'),
        Request::json('/api/agent-auth'),
        Request::json('/api/agent-auth'),
        Request::json('/api/agent-auth'),
        Request::json('/api/agent-auth'),
    ]);
    expect($browser)->toHavePending([]);
    expect($output)->toMatchLog(<<<'OUTPUT'
        {date} {info} Authentication successful {duration}
        {date} {info} Authentication failed {duration}: 401 \[{"message":"Missing token"}\]
        {date} {info} Authentication failed {duration}: 401 \[{"message":"Missing token"}\]
        {date} {info} Authentication failed {duration}: 401 \[{"message":"Invalid environment token"}\]
        {date} {info} Authentication failed {duration}: 401 \[{"message":"Invalid environment token"}\]
        OUTPUT);
});

it('limits response body included in logs', function () {
    $loop = new LoopFake(runForSeconds: 2.5 + 5);
    $browser = new BrowserFake([
        Response::internalServerError(str_repeat('a', 255)),
        Response::internalServerError(str_repeat('a', 256)),
    ]);

    [$output, $e] = run(
        via: 'source',
        ingestDetailsBrowser: $browser,
        loop: $loop,
    );

    expect($e)->toBeNull($e?->getMessage() ?? '');
    $scheduledBy = 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn';
    expect($loop)->toHaveRun([
        new Timer(interval: 2.5, runAt: 2.5, scheduledAt: 0, scheduledBy: $scheduledBy),
    ]);
    expect($loop)->toHavePending([
        new Timer(interval: 5, runAt: 7.5, scheduledAt: 2.5, scheduledBy: $scheduledBy),
    ]);
    expect($browser)->toHaveSent([
        Request::json('/api/agent-auth'),
        Request::json('/api/agent-auth'),
    ]);
    expect($browser)->toHavePending([]);
    $firstBody = str_repeat('a', 255);
    $secondBody = str_repeat('a', 250);
    expect($output)->toMatchLog(<<<OUTPUT
        {date} {info} Authentication failed {duration}: 500 \[{$firstBody}\]
        {date} {info} Authentication failed {duration}: 500 \[{$secondBody}\[\.\.\.\]\]
        OUTPUT);
});
