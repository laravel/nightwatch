<?php

use Tests\BrowserFake;
use Tests\LoopFake;
use Tests\Request;
use Tests\Response;
use Tests\Timer;

it('handles runtime exceptions while procesing the request', function () {
    $loop = new LoopFake;
    $browser = new BrowserFake([
        [RuntimeException::class, 'Whoops!'],
    ]);

    [$output, $e] = run(
        via: 'source',
        browser: $browser,
        loop: $loop,
    );

    expect($e)->toBeNull($e?->getMessage() ?? '');
    expect($browser)->toHaveSent([
        new Request('/api/agent-auth'),
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
        new Response('Whoops!', status: 400),
    ]);

    [$output, $e] = run(
        via: 'source',
        browser: $browser,
        loop: $loop,
    );

    expect($e)->toBeNull($e?->getMessage() ?? '');
    expect($browser)->toHaveSent([
        new Request('/api/agent-auth'),
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
        new Response('Whoops!', status: 500),
    ]);

    [$output, $e] = run(
        via: 'source',
        browser: $browser,
        loop: $loop,
    );

    expect($e)->toBeNull($e?->getMessage() ?? '');
    expect($browser)->toHaveSent([
        new Request('/api/agent-auth'),
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
        browser: $browser,
        loop: $loop,
    );

    expect($e)->toBeNull($e?->getMessage() ?? '');
    expect($browser)->toHaveSent([
        new Request('/api/agent-auth'),
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
        browser: $browser,
        loop: $loop,
    );

    expect($e)->toBeNull($e?->getMessage() ?? '');
    expect($browser)->toHaveSent([
        new Request('/api/agent-auth'),
    ]);
    expect($browser)->toHavePending([]);
    $payload = preg_quote(json_encode($payload, flags: JSON_THROW_ON_ERROR), '#');
    expect($output)->toMatchLog(<<<OUTPUT
        {date} {info} Authentication failed {duration}: Invalid authentication response \[{$payload}\].
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
        browser: $browser,
        loop: $loop,
    );

    expect($e)->toBeNull($e?->getMessage() ?? '');
    expect($browser)->toHaveSent([
        new Request('/api/agent-auth'),
    ]);
    expect($browser)->toHavePending([]);
    expect($output)->toMatchLog(<<<'OUTPUT'
        {date} {info} Authentication successful {duration}
        OUTPUT);
    expect($loop)->toHaveRun([]);
});

it('refreshes the token based on refresh_in', function () {
    $loop = new LoopFake(runForSeconds: 5 + 10 + 3_600 + 300 + 1);
    $browser = new BrowserFake(pendingResponses: [
        Response::jwt(refreshIn: 5),

        Response::jwt(refreshIn: 10),
        new Response(status: 500),
        Response::jwt(),
        Response::jwt(),
    ]);

    [$output, $e] = run(
        via: 'source',
        browser: $browser,
        loop: $loop,
    );

    expect($e)->toBeNull($e?->getMessage() ?? '');
    $scheduleRefreshIn = 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn';
    expect($loop)->toHaveRun([
        new Timer(interval: 5, runAt: 5, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 10, runAt: 5 + 10, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 5 + 10 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 3_600, runAt: 5 + 10 + 300 + 3_600, scheduledBy: $scheduleRefreshIn),
    ]);
    expect($loop)->toHavePending([
        new Timer(interval: 3_600, runAt: 5 + 10 + 300 + 3_600 + 3_600, scheduledBy: $scheduleRefreshIn),
    ]);
    expect($browser)->toHaveSent([
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
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
        [RuntimeException::class, 'Whoops 1!'], // 0s

        [RuntimeException::class, 'Whoops 2!'], // 2.5s
        [RuntimeException::class, 'Whoops 3!'], // 5s
        [RuntimeException::class, 'Whoops 4!'], // 10s
        [RuntimeException::class, 'Whoops 5!'], // 15s
        [RuntimeException::class, 'Whoops 6!'], // 30s
        [RuntimeException::class, 'Whoops 7!'], // 60s
        [RuntimeException::class, 'Whoops 8!'], // 120s
        [RuntimeException::class, 'Whoops 9!'], // 240s
        [RuntimeException::class, 'Whoops 10!'], // 300s
        [RuntimeException::class, 'Whoops 11!'], // 300s
        [RuntimeException::class, 'Whoops 12!'], // 300s
        [RuntimeException::class, 'Whoops 13!'], // 300s
        [RuntimeException::class, 'Whoops 14!'], // 300s
        [RuntimeException::class, 'Whoops 15!'], // 300s
        [RuntimeException::class, 'Whoops 16!'], // 300s
        [RuntimeException::class, 'Whoops 17!'], // 300s
        [RuntimeException::class, 'Whoops 18!'], // 300s
        [RuntimeException::class, 'Whoops 19!'], // 300s
        [RuntimeException::class, 'Whoops 20!'], // 300s
        [RuntimeException::class, 'Whoops 21!'], // 300s
        [RuntimeException::class, 'Whoops 22!'], // 1h
        [RuntimeException::class, 'Whoops 23!'], // 1h
        [RuntimeException::class, 'Whoops 24!'], // 1h
    ]);

    [$output, $e] = run(
        via: 'source',
        browser: $browser,
        loop: $loop,
    );

    expect($e)->toBeNull($e?->getMessage() ?? '');
    $scheduleRefreshIn = 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn';
    expect($loop)->toHaveRun([
        new Timer(interval: 2.5, runAt: 2.5, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 5, runAt: 2.5 + 5, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 10, runAt: 2.5 + 5 + 10, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 15, runAt: 2.5 + 5 + 10 + 15, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 30, runAt: 2.5 + 5 + 10 + 15 + 30, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 60, runAt: 2.5 + 5 + 10 + 15 + 30 + 60, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 120, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 240, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 3_600, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 3_600, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600 + 3_600, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 3_600, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600 + 3_600 + 3_600, scheduledBy: $scheduleRefreshIn),
    ]);
    expect($loop)->toHavePending([
        new Timer(interval: 3_600, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600 + 3_600 + 3_600 + 3_600, scheduledBy: $scheduleRefreshIn),
    ]);
    expect($browser)->toHaveSent([
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
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
        new Response('Whoops 1!', status: 500), // 0s

        new Response('Whoops 2!', status: 501), // 2.5s
        new Response('Whoops 3!', status: 400), // 5s
        new Response('Whoops 4!', status: 402), // 10s
        new Response('Whoops 5!', status: 500), // 30s
        new Response('Whoops 6!', status: 500), // 60s
        new Response('Whoops 7!', status: 500), // 120s
        new Response('Whoops 8!', status: 500), // 240s
        new Response('Whoops 9!', status: 500), // 300s
        new Response('Whoops 10!', status: 500), // 300s
        new Response('Whoops 11!', status: 500), // 300s
        new Response('Whoops 12!', status: 500), // 300s
        new Response('Whoops 13!', status: 500), // 300s
        new Response('Whoops 14!', status: 500), // 300s
        new Response('Whoops 15!', status: 500), // 300s
        new Response('Whoops 16!', status: 500), // 300s
        new Response('Whoops 17!', status: 500), // 300s
        new Response('Whoops 18!', status: 500), // 300s
        new Response('Whoops 19!', status: 500), // 300s
        new Response('Whoops 20!', status: 500), // 300s
        new Response('Whoops 21!', status: 500), // 1h
        new Response('Whoops 22!', status: 500), // 1h
        new Response('Whoops 23!', status: 500), // 1h
        new Response('Whoops 24!', status: 500), // 1h
    ]);

    [$output, $e] = run(
        via: 'source',
        browser: $browser,
        loop: $loop,
    );

    expect($e)->toBeNull($e?->getMessage() ?? '');
    $scheduleRefreshIn = 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn';
    expect($loop)->toHaveRun([
        new Timer(interval: 2.5, runAt: 2.5, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 5, runAt: 2.5 + 5, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 10, runAt: 2.5 + 5 + 10, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 15, runAt: 2.5 + 5 + 10 + 15, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 30, runAt: 2.5 + 5 + 10 + 15 + 30, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 60, runAt: 2.5 + 5 + 10 + 15 + 30 + 60, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 120, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 240, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 3_600, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 3_600, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600 + 3_600, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 3_600, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600 + 3_600 + 3_600, scheduledBy: $scheduleRefreshIn),
    ]);
    expect($loop)->toHavePending([
        new Timer(interval: 3_600, runAt: 2.5 + 5 + 10 + 15 + 30 + 60 + 120 + 240 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600 + 3_600 + 3_600 + 3_600, scheduledBy: $scheduleRefreshIn),
    ]);
    expect($browser)->toHavePending([]);
    expect($browser)->toHaveSent([
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
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
        new Response('{"message":"Missing token"}', 401),

        new Response('{"message":"Missing token"}', 401),
        new Response('{"message":"Invalid environment token"}', 401),
        new Response('{"message":"Invalid environment token"}', 401),
    ]);

    [$output, $e] = run(
        via: 'source',
        browser: $browser,
        loop: $loop,
    );

    expect($e)->toBeNull($e?->getMessage() ?? '');
    $scheduleRefreshIn = 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn';
    expect($loop)->toHaveRun([
        new Timer(interval: 3_600, runAt: 3_600, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 3_600, runAt: 3_600 + 3_600, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 3_600, runAt: 3_600 + 3_600 + 3_600, scheduledBy: $scheduleRefreshIn),
    ]);
    expect($loop)->toHavePending([
        new Timer(interval: 3_600, runAt: 3_600 + 3_600 + 3_600 + 3_600, scheduledBy: $scheduleRefreshIn),
    ]);
    expect($browser)->toHaveSent([
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
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

        [RuntimeException::class, 'Whoops 1!'], // 300s
        [RuntimeException::class, 'Whoops 2!'], // 300s
        [RuntimeException::class, 'Whoops 3!'], // 300s
        [RuntimeException::class, 'Whoops 4!'], // 300s
        [RuntimeException::class, 'Whoops 5!'], // 300s
        [RuntimeException::class, 'Whoops 6!'], // 300s
        [RuntimeException::class, 'Whoops 7!'], // 300s
        [RuntimeException::class, 'Whoops 8!'], // 300s
        [RuntimeException::class, 'Whoops 9!'], // 300s
        [RuntimeException::class, 'Whoops 10!'], // 300s
        [RuntimeException::class, 'Whoops 11!'], // 300s
        [RuntimeException::class, 'Whoops 12!'], // 300s
        [RuntimeException::class, 'Whoops 13!'], // 3_600s
        [RuntimeException::class, 'Whoops 14!'], // 3_600s
        [RuntimeException::class, 'Whoops 15!'], // 3_600s
        [RuntimeException::class, 'Whoops 16!'], // 3_600s
    ]);

    [$output, $e] = run(
        via: 'source',
        browser: $browser,
        loop: $loop,
    );

    expect($e)->toBeNull($e?->getMessage() ?? '');
    $scheduleRefreshIn = 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn';
    expect($loop)->toHaveRun([
        new Timer(interval: 3_600, runAt: 3_600, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 3_600 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 3_600 + 300 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 3_600 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 3_600 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 3_600 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 3_600, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 3_600, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600 + 3_600, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 3_600, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600 + 3_600 + 3_600, scheduledBy: $scheduleRefreshIn),
    ]);
    expect($loop)->toHavePending([
        new Timer(interval: 3_600, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600 + 3_600 + 3_600 + 3_600, scheduledBy: $scheduleRefreshIn),
    ]);
    expect($browser)->toHaveSent([
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
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

        new Response('Whoops 1!', status: 500), // 300s
        new Response('Whoops 2!', status: 501), // 300s
        new Response('Whoops 3!', status: 400), // 300s
        new Response('Whoops 4!', status: 402), // 300s
        new Response('Whoops 5!', status: 500), // 300s
        new Response('Whoops 6!', status: 500), // 300s
        new Response('Whoops 7!', status: 500), // 300s
        new Response('Whoops 8!', status: 500), // 300s
        new Response('Whoops 9!', status: 500), // 300s
        new Response('Whoops 10!', status: 500), // 300s
        new Response('Whoops 11!', status: 500), // 300s
        new Response('Whoops 12!', status: 500), // 300s
        new Response('Whoops 13!', status: 500), // 3_600s
        new Response('Whoops 14!', status: 500), // 3_600s
        new Response('Whoops 15!', status: 500), // 3_600s
        new Response('Whoops 16!', status: 500), // 3_600s
    ]);

    [$output, $e] = run(
        via: 'source',
        browser: $browser,
        loop: $loop,
    );

    expect($e)->toBeNull($e?->getMessage() ?? '');
    $scheduleRefreshIn = 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn';
    expect($loop)->toHaveRun([
        new Timer(interval: 3_600, runAt: 3_600, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 3_600 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 3_600 + 300 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 3_600 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 3_600 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 3_600 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 300, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 3_600, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 3_600, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600 + 3_600, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 3_600, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600 + 3_600 + 3_600, scheduledBy: $scheduleRefreshIn),
    ]);
    expect($loop)->toHavePending([
        new Timer(interval: 3_600, runAt: 3_600 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 300 + 3_600 + 3_600 + 3_600 + 3_600, scheduledBy: $scheduleRefreshIn),
    ]);
    expect($browser)->toHaveSent([
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
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

        new Response('{"message":"Missing token"}', 401),
        new Response('{"message":"Missing token"}', 401),
        new Response('{"message":"Invalid environment token"}', 401),
        new Response('{"message":"Invalid environment token"}', 401),
    ]);

    [$output, $e] = run(
        via: 'source',
        browser: $browser,
        loop: $loop,
    );

    expect($e)->toBeNull($e?->getMessage() ?? '');
    $scheduleRefreshIn = 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn';
    expect($loop)->toHaveRun([
        new Timer(interval: 3_600, runAt: 3_600, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 3_600, runAt: 3_600 + 3_600, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 3_600, runAt: 3_600 + 3_600 + 3_600, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 3_600, runAt: 3_600 + 3_600 + 3_600 + 3_600, scheduledBy: $scheduleRefreshIn),
    ]);
    expect($loop)->toHavePending([
        new Timer(interval: 3_600, runAt: 3_600 + 3_600 + 3_600 + 3_600 + 3_600, scheduledBy: $scheduleRefreshIn),
    ]);
    expect($browser)->toHaveSent([
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
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
    $browser = new BrowserFake(pendingResponses: [
        new Response(str_repeat('a', 255), 500),
        new Response(str_repeat('a', 256), 500),
    ]);

    [$output, $e] = run(
        via: 'source',
        browser: $browser,
        loop: $loop,
    );

    expect($e)->toBeNull($e?->getMessage() ?? '');
    $scheduledBy = 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn';
    expect($loop)->toHaveRun([
        new Timer(interval: 2.5, runAt: 2.5, scheduledBy: $scheduledBy),
    ]);
    expect($loop)->toHavePending([
        new Timer(interval: 5, runAt: 7.5, scheduledBy: $scheduledBy),
    ]);
    expect($browser)->toHaveSent([
        new Request('/api/agent-auth'),
        new Request('/api/agent-auth'),
    ]);
    expect($browser)->toHavePending([]);
    $firstBody = str_repeat('a', 255);
    $secondBody = str_repeat('a', 250);
    expect($output)->toMatchLog(<<<OUTPUT
        {date} {info} Authentication failed {duration}: 500 \[{$firstBody}\]
        {date} {info} Authentication failed {duration}: 500 \[{$secondBody}\[\.\.\.\]\]
        OUTPUT);
});
