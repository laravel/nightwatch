<?php

use Tests\BrowserFake;
use Tests\Connection;
use Tests\LoopFake;
use Tests\Request;
use Tests\Response;
use Tests\TcpServerFake;
use Tests\Timer;

it('server responds with OK', function () {
    $loop = new LoopFake(runForSeconds: 2);
    $server = new TcpServerFake;
    $ingestDetailsBrowser = new BrowserFake([Response::jwt()]);
    $ingestBrowser = new BrowserFake;

    $loop->addTimer(1, $server->pendingConnection([['t' => 'request']]));

    [$output, $e] = run(
        via: 'source',
        ingestDetailsBrowser: $ingestDetailsBrowser,
        ingestBrowser: $ingestBrowser,
        loop: $loop,
        server: $server,
    );

    expect($e)->toBeNull($e?->getMessage() ?? '');
    expect($server)->toHaveConnections([
        Connection::closed('2:OK'),
    ]);
    expect($server->closed)->toBeFalse();
    expect($output)->toMatchLog(<<<'OUTPUT'
        {date} {info} Authentication successful {duration}
        OUTPUT);
    expect($loop)->toHaveRun([
        new Timer(interval: 1, runAt: 1, scheduledAt: 0, scheduledBy: self::class),
    ]);
    expect($loop)->toHavePending([
        new Timer(interval: 10, runAt: 11, scheduledAt: 1, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
        new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
    ]);
    expect($ingestDetailsBrowser)->toHaveSent([
        Request::json('/api/agent-auth'),
    ]);
    expect($ingestDetailsBrowser)->toHavePending([]);
    expect($ingestBrowser)->toHaveSentNothing();
    expect($ingestBrowser)->toHavePending([]);
});

it('can ping the server', function () {
    $loop = new LoopFake(runForSeconds: 1);
    $server = new TcpServerFake;
    $ingestDetailsBrowser = new BrowserFake([Response::jwt()]);
    $ingestBrowser = new BrowserFake;
    $signature = signature();

    $loop->addTimer(0, $server->pendingConnection("12:{$signature}:PING"));

    [$output, $e] = run(
        via: 'source',
        ingestDetailsBrowser: $ingestDetailsBrowser,
        ingestBrowser: $ingestBrowser,
        loop: $loop,
        server: $server,
    );

    expect($e)->toBeNull($e?->getMessage() ?? '');
    expect($server)->toHaveConnections([
        Connection::closed('2:OK'),
    ]);
    expect($server->closed)->toBeFalse();
    expect($output)->toMatchLog(<<<'OUTPUT'
        {date} {info} Authentication successful {duration}
        OUTPUT);
    expect($loop)->toHaveRun([
        new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: self::class),
    ]);
    expect($loop)->toHavePending([
        new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
    ]);
    expect($ingestDetailsBrowser)->toHaveSent([
        Request::json('/api/agent-auth'),
    ]);
    expect($ingestDetailsBrowser)->toHavePending([]);
    expect($ingestBrowser)->toHaveSentNothing();
    expect($ingestBrowser)->toHavePending([]);
});

it('stops loop when an incorrect signature is received', function () {
    $loop = new LoopFake(runForSeconds: 2);
    $server = new TcpServerFake;
    $ingestDetailsBrowser = new BrowserFake([Response::jwt()]);
    $ingestBrowser = new BrowserFake;

    $loop->addTimer(1, $server->pendingConnection('12:INVALID:[{}]'));

    [$output, $e] = run(
        via: 'source',
        ingestDetailsBrowser: $ingestDetailsBrowser,
        ingestBrowser: $ingestBrowser,
        loop: $loop,
        server: $server,
    );

    expect($e)->toBeNull($e?->getMessage() ?? '');
    expect($server)->toHaveConnections([
        Connection::closed('2:OK'),
    ]);
    expect($server->closed)->toBeTrue();
    expect($output)->toMatchLog(<<<'OUTPUT'
        {date} {info} Authentication successful {duration}
        {date} {info} Incoming signature has changed
        {date} {info} Shutting down
        OUTPUT);
    expect($loop)->toHaveRun([
        new Timer(interval: 1, runAt: 1, scheduledAt: 0, scheduledBy: self::class),
    ]);
    expect($loop)->toHavePending([
        new Timer(interval: 3_600, runAt: null, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
    ]);
    expect($loop->stopped)->toBeTrue();
    expect($ingestDetailsBrowser)->toHaveSent([
        Request::json('/api/agent-auth'),
    ]);
    expect($ingestDetailsBrowser)->toHavePending([]);
    expect($ingestBrowser)->toHaveSentNothing();
    expect($ingestBrowser)->toHavePending([]);
});
