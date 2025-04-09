<?php

use Tests\BrowserFake;
use Tests\LoopFake;
use Tests\Request;
use Tests\Response;
use Tests\TcpServerFake;
use Tests\Timer;

it('can ingests records', function () {
    $loop = new LoopFake(runForSeconds: 1);
    $server = new TcpServerFake;
    $ingestDetailsBrowser = new BrowserFake([
        Response::jwt(),
    ]);
    $ingestBrowser = new BrowserFake([
        new Response,
    ]);
    $records = array_fill(0, 375_001, ['t' => 'request']);
    $loop->addTimer(0, $server->pendingConnection($records));

    [$output, $e] = run(
        via: 'source',
        ingestDetailsBrowser: $ingestDetailsBrowser,
        ingestBrowser: $ingestBrowser,
        loop: $loop,
        server: $server,
    );

    expect($e)->toBeNull($e?->getMessage() ?? '');
    expect($output)->toMatchLog(<<<'OUTPUT'
        {date} {info} Authentication successful {duration}
        {date} {info} Ingest successful {duration}
        OUTPUT);
    expect($ingestBrowser->timeout)->toBe(10.0);
    expect($ingestBrowser->connectionTimeout)->toBe(5.0);
    expect($ingestBrowser->baseUrl)->toBeNull();
    expect($ingestBrowser->headers)->toBe([
        'accept' => 'application/json',
        'content-encoding' => 'gzip',
        'content-type' => 'application/json',
        'nightwatch-server' => gethostname(),
    ]);
    expect($ingestBrowser)->toHaveSent([
        Request::ingest($records),
    ]);
    expect($ingestBrowser)->toHavePending([]);
    expect($loop)->toHaveRun([
        new Timer(interval: 0, runAt: 0, scheduledBy: self::class),
    ]);
    expect($loop)->toHavePending([
        new Timer(interval: 3_600, runAt: 3_600, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
    ]);
    expect($ingestDetailsBrowser)->toHaveSent([
        Request::json('/api/agent-auth'),
    ]);
    expect($ingestDetailsBrowser)->toHavePending([]);
});

it('handles unsuccessful responses', function () {
    $loop = new LoopFake(runForSeconds: 1);
    $server = new TcpServerFake;
    $ingestDetailsBrowser = new BrowserFake([
        Response::jwt(),
    ]);
    $ingestBrowser = new BrowserFake([
        new Response('Whoops!', status: 500),
    ]);
    $records = array_fill(0, 375_001, ['t' => 'request']);
    $loop->addTimer(0, $server->pendingConnection($records));

    [$output, $e] = run(
        via: 'source',
        ingestDetailsBrowser: $ingestDetailsBrowser,
        ingestBrowser: $ingestBrowser,
        loop: $loop,
        server: $server,
    );

    expect($e)->toBeNull($e?->getMessage() ?? '');
    expect($output)->toMatchLog(<<<'OUTPUT'
        {date} {info} Authentication successful {duration}
        {date} {info} Ingest failed {duration}: 500 \[Whoops!\]
        OUTPUT);
    expect($ingestBrowser->timeout)->toBe(10.0);
    expect($ingestBrowser->connectionTimeout)->toBe(5.0);
    expect($ingestBrowser->baseUrl)->toBeNull();
    expect($ingestBrowser->headers)->toBe([
        'accept' => 'application/json',
        'content-encoding' => 'gzip',
        'content-type' => 'application/json',
        'nightwatch-server' => gethostname(),
    ]);
    expect($ingestBrowser)->toHaveSent([
        Request::ingest($records),
    ]);
    expect($ingestBrowser)->toHavePending([]);
    expect($loop)->toHaveRun([
        new Timer(interval: 0, runAt: 0, scheduledBy: self::class),
    ]);
    expect($loop)->toHavePending([
        new Timer(interval: 3_600, runAt: 3_600, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
    ]);
    expect($ingestDetailsBrowser)->toHaveSent([
        Request::json('/api/agent-auth'),
    ]);
    expect($ingestDetailsBrowser)->toHavePending([]);
});

it('handles runtime exceptions while procesing the request', function () {
    $loop = new LoopFake(runForSeconds: 1);
    $server = new TcpServerFake;
    $ingestDetailsBrowser = new BrowserFake([
        Response::jwt(),
    ]);
    $ingestBrowser = new BrowserFake([
        [RuntimeException::class, 'Whoops!'],
    ]);
    $records = array_fill(0, 375_001, ['t' => 'request']);
    $loop->addTimer(0, $server->pendingConnection($records));

    [$output, $e] = run(
        via: 'source',
        ingestDetailsBrowser: $ingestDetailsBrowser,
        ingestBrowser: $ingestBrowser,
        loop: $loop,
        server: $server,
    );

    expect($e)->toBeNull($e?->getMessage() ?? '');
    expect($output)->toMatchLog(<<<'OUTPUT'
        {date} {info} Authentication successful {duration}
        {date} {info} Ingest failed {duration}: Whoops!
        OUTPUT);
    expect($ingestBrowser->timeout)->toBe(10.0);
    expect($ingestBrowser->connectionTimeout)->toBe(5.0);
    expect($ingestBrowser->baseUrl)->toBeNull();
    expect($ingestBrowser->headers)->toBe([
        'accept' => 'application/json',
        'content-encoding' => 'gzip',
        'content-type' => 'application/json',
        'nightwatch-server' => gethostname(),
    ]);
    expect($ingestBrowser)->toHaveSent([
        Request::ingest($records),
    ]);
    expect($ingestBrowser)->toHavePending([]);
    expect($loop)->toHaveRun([
        new Timer(interval: 0, runAt: 0, scheduledBy: self::class),
    ]);
    expect($loop)->toHavePending([
        new Timer(interval: 3_600, runAt: 3_600, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
    ]);
    expect($ingestDetailsBrowser)->toHaveSent([
        Request::json('/api/agent-auth'),
    ]);
    expect($ingestDetailsBrowser)->toHavePending([]);
});

it('handles missing authentication details', function () {
    $loop = new LoopFake(runForSeconds: 1);
    $server = new TcpServerFake;
    $ingestDetailsBrowser = new BrowserFake([
        Response::unauthenticated(),
    ]);
    $ingestBrowser = new BrowserFake([]);
    $records = array_fill(0, 375_001, ['t' => 'request']);
    $loop->addTimer(0, $server->pendingConnection($records));

    [$output, $e] = run(
        via: 'source',
        ingestDetailsBrowser: $ingestDetailsBrowser,
        ingestBrowser: $ingestBrowser,
        loop: $loop,
        server: $server,
    );

    expect($e)->toBeNull($e?->getMessage() ?? '');
    expect($output)->toMatchLog(<<<'OUTPUT'
        {date} {info} Authentication failed {duration}: 401 \[{"message":"Invalid environment token"}\]
        {date} {info} Ingest failed {duration}: No authentication details\.
        OUTPUT);
    expect($ingestBrowser->timeout)->toBe(10.0);
    expect($ingestBrowser->connectionTimeout)->toBe(5.0);
    expect($ingestBrowser->baseUrl)->toBeNull();
    expect($ingestBrowser->headers)->toBe([
        'accept' => 'application/json',
        'content-encoding' => 'gzip',
        'content-type' => 'application/json',
        'nightwatch-server' => gethostname(),
    ]);
    expect($ingestBrowser)->toHaveSent([]);
    expect($ingestBrowser)->toHavePending([]);
    expect($loop)->toHaveRun([
        new Timer(interval: 0, runAt: 0, scheduledBy: self::class),
    ]);
    expect($loop)->toHavePending([
        new Timer(interval: 3_600, runAt: 3_600, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
    ]);
    expect($ingestDetailsBrowser)->toHaveSent([
        Request::json('/api/agent-auth'),
    ]);
    expect($ingestDetailsBrowser)->toHavePending([]);
});

it('limits response body included in logs', function () {
    $loop = new LoopFake(runForSeconds: 2);
    $server = new TcpServerFake;
    $ingestDetailsBrowser = new BrowserFake([
        Response::jwt(),
    ]);
    $ingestBrowser = new BrowserFake([
        new Response(str_repeat('a', 255), 500),
        new Response(str_repeat('a', 256), 500),
    ]);
    $records = array_fill(0, 375_001, ['t' => 'request']);
    $loop->addTimer(0, $server->pendingConnection($records));
    $loop->addTimer(1, $server->pendingConnection($records));

    [$output, $e] = run(
        via: 'source',
        ingestDetailsBrowser: $ingestDetailsBrowser,
        ingestBrowser: $ingestBrowser,
        loop: $loop,
        server: $server,
    );

    expect($e)->toBeNull($e?->getMessage() ?? '');
    $firstBody = str_repeat('a', 255);
    $secondBody = str_repeat('a', 250);
    expect($output)->toMatchLog(<<<OUTPUT
        {date} {info} Authentication successful {duration}
        {date} {info} Ingest failed {duration}: 500 \[{$firstBody}\]
        {date} {info} Ingest failed {duration}: 500 \[{$secondBody}\[\.\.\.\]\]
        OUTPUT);
    expect($ingestBrowser->timeout)->toBe(10.0);
    expect($ingestBrowser->connectionTimeout)->toBe(5.0);
    expect($ingestBrowser->baseUrl)->toBeNull();
    expect($ingestBrowser->headers)->toBe([
        'accept' => 'application/json',
        'content-encoding' => 'gzip',
        'content-type' => 'application/json',
        'nightwatch-server' => gethostname(),
    ]);
    expect($ingestBrowser)->toHaveSent([
        Request::ingest($records),
        Request::ingest($records),
    ]);
    expect($ingestBrowser)->toHavePending([]);
    expect($loop)->toHaveRun([
        new Timer(interval: 0, runAt: 0, scheduledBy: self::class),
        new Timer(interval: 1, runAt: 1, scheduledBy: self::class),
    ]);
    expect($loop)->toHavePending([
        new Timer(interval: 3_600, runAt: 3_600, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
    ]);
    expect($ingestDetailsBrowser)->toHaveSent([
        Request::json('/api/agent-auth'),
    ]);
    expect($ingestDetailsBrowser)->toHavePending([]);
});
