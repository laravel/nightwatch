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
        Response::internalServerError('Whoops!'),
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
        Response::throwWhileProcessing('Whoops!'),
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
        {date} {info} Ingest failed {duration}: No authentication details
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
        Response::internalServerError(str_repeat('a', 255)),
        Response::internalServerError(str_repeat('a', 256)),
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

it('waits on the resolution of the ingest details before attempting to ingest', function (int $duration, string $log) {
    $loop = new LoopFake(runForSeconds: 2);
    $server = new TcpServerFake;
    $ingestDetailsBrowser = new BrowserFake([
        Response::jwt(duration: $duration),
    ]);
    $ingestBrowser = new BrowserFake([
        new Response('Ingest successful'),
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
    expect($output)->toMatchLog($log);
    expect($ingestBrowser)->toHaveSent($duration === 1
        ? [Request::ingest($records)]
        : []);
    expect($ingestBrowser)->toBeProcessing([]);
    expect($ingestBrowser)->toHavePending($duration === 1
        ? []
        : [new Response('Ingest successful')]);
    expect($loop)->toHaveRun([
        new Timer(interval: 0, runAt: 0, scheduledBy: self::class),
        ...($duration === 1
            ? [new Timer(interval: $duration, runAt: $duration, scheduledBy: 'Tests\Response::toPromise')]
            : []),
    ]);
    expect($loop)->toHavePending($duration === 1
        ? [new Timer(interval: 3_600, runAt: 3_601, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn')]
        : [new Timer(interval: $duration, runAt: $duration, scheduledBy: 'Tests\Response::toPromise')]);
    expect($ingestDetailsBrowser)->toHaveSent([
        Request::json('/api/agent-auth'),
    ]);
    expect($ingestDetailsBrowser)->toBeProcessing($duration === 1
        ? []
        : [Response::jwt(duration: $duration)]);
    expect($ingestDetailsBrowser)->toHavePending([]);
})->with([
    [1, <<<'LOG'
        {date} {info} Authentication successful {duration}
        {date} {info} Ingest successful {duration}
        LOG],
    [2, ''],
]);

it('handles runtime errors while waiting to authenticate', function () {
    $loop = new LoopFake(runForSeconds: 2);
    $server = new TcpServerFake;
    $ingestDetailsBrowser = new BrowserFake([
        Response::throwWhileProcessing('Whoops!', duration: 1),
    ]);
    $ingestBrowser = new BrowserFake([
        //
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
        {date} {info} Authentication failed {duration}: Whoops!
        {date} {info} Ingest failed {duration}: No authentication details
        OUTPUT);
    expect($ingestBrowser)->toHaveSent([]);
    expect($ingestBrowser)->toHavePending([]);
    expect($loop)->toHaveRun([
        new Timer(interval: 0, runAt: 0, scheduledBy: self::class),
        new Timer(interval: 1, runAt: 1, scheduledBy: 'Tests\Response::toPromise'),
    ]);
    expect($loop)->toHavePending([
        new Timer(interval: 2.5, runAt: 3.5, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
    ]);
    expect($ingestDetailsBrowser)->toHaveSent([
        Request::json('/api/agent-auth'),
    ]);
    expect($ingestDetailsBrowser)->toHavePending([]);
});

it('handles error responses while waiting to authenticate', function () {
    $loop = new LoopFake(runForSeconds: 2);
    $server = new TcpServerFake;
    $ingestDetailsBrowser = new BrowserFake([
        Response::internalServerError('Whoops!', duration: 1),
    ]);
    $ingestBrowser = new BrowserFake([
        //
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
        {date} {info} Authentication failed {duration}: 500 \[Whoops!\]
        {date} {info} Ingest failed {duration}: No authentication details
        OUTPUT);
    expect($ingestBrowser)->toHaveSent([]);
    expect($ingestBrowser)->toHavePending([]);
    expect($loop)->toHaveRun([
        new Timer(interval: 0, runAt: 0, scheduledBy: self::class),
        new Timer(interval: 1, runAt: 1, scheduledBy: 'Tests\Response::toPromise'),
    ]);
    expect($loop)->toHavePending([
        new Timer(interval: 2.5, runAt: 3.5, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
    ]);
    expect($ingestDetailsBrowser)->toHaveSent([
        Request::json('/api/agent-auth'),
    ]);
    expect($ingestDetailsBrowser)->toHavePending([]);
});

it('can have two concurrent ingest requests', function () {
    $loop = new LoopFake(runForSeconds: 10);
    $server = new TcpServerFake;
    $ingestDetailsBrowser = new BrowserFake([
        Response::jwt(),
    ]);
    $ingestBrowser = new BrowserFake([
        new Response(duration: 3),
        new Response(duration: 4),
    ]);
    $records = array_fill(0, 375_001, ['t' => 'request']);
    $loop->addTimer(0, $server->pendingConnection($records));
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
        {date} {info} Ingest successful {duration}
        OUTPUT);
    expect($ingestBrowser)->toHaveSent([
        Request::ingest($records),
        Request::ingest($records),
    ]);
    expect($ingestDetailsBrowser)->toBeProcessing([]);
    expect($ingestBrowser)->toHavePending([]);
    expect($loop)->toHaveRun([
        new Timer(interval: 0, runAt: 0, scheduledBy: self::class),
        new Timer(interval: 0, runAt: 0, scheduledBy: self::class),
        new Timer(interval: 3, runAt: 3, scheduledBy: 'Tests\Response::toPromise'),
        new Timer(interval: 4, runAt: 4, scheduledBy: 'Tests\Response::toPromise'),
    ]);
    expect($loop)->toHavePending([
        new Timer(interval: 3_600, runAt: 3_600, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
    ]);
    expect($ingestDetailsBrowser)->toHaveSent([
        Request::json('/api/agent-auth'),
    ]);
    expect($ingestDetailsBrowser)->toBeProcessing([]);
    expect($ingestDetailsBrowser)->toHavePending([]);
});

it('can have no more than two concurrent ingest requests', function () {
    $loop = new LoopFake(runForSeconds: 10);
    $server = new TcpServerFake;
    $ingestDetailsBrowser = new BrowserFake([
        Response::jwt(),
    ]);
    $ingestBrowser = new BrowserFake([
        new Response(duration: 3),
        new Response(duration: 4),
    ]);
    $records = array_fill(0, 375_001, ['t' => 'request']);
    $loop->addTimer(0, $server->pendingConnection($records));
    $loop->addTimer(0, $server->pendingConnection($records));
    $loop->addTimer(0, $server->pendingConnection($records));
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
        {date} {info} Ingest failed {duration}: Exceeded concurrent request limit\. \[2\] requests are processing
        {date} {info} Ingest failed {duration}: Exceeded concurrent request limit\. \[2\] requests are processing
        {date} {info} Ingest successful {duration}
        {date} {info} Ingest successful {duration}
        OUTPUT);
    expect($ingestBrowser)->toHaveSent([
        Request::ingest($records),
        Request::ingest($records),
    ]);
    expect($ingestDetailsBrowser)->toBeProcessing([]);
    expect($ingestBrowser)->toHavePending([]);
    expect($loop)->toHaveRun([
        new Timer(interval: 0, runAt: 0, scheduledBy: self::class),
        new Timer(interval: 0, runAt: 0, scheduledBy: self::class),
        new Timer(interval: 0, runAt: 0, scheduledBy: self::class),
        new Timer(interval: 0, runAt: 0, scheduledBy: self::class),
        new Timer(interval: 3, runAt: 3, scheduledBy: 'Tests\Response::toPromise'),
        new Timer(interval: 4, runAt: 4, scheduledBy: 'Tests\Response::toPromise'),
    ]);
    expect($loop)->toHavePending([
        new Timer(interval: 3_600, runAt: 3_600, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
    ]);
    expect($ingestDetailsBrowser)->toHaveSent([
        Request::json('/api/agent-auth'),
    ]);
    expect($ingestDetailsBrowser)->toBeProcessing([]);
    expect($ingestDetailsBrowser)->toHavePending([]);
});

it('can have two concurrent requests ongoing', function () {
    $loop = new LoopFake(runForSeconds: 14);
    $server = new TcpServerFake;
    $ingestDetailsBrowser = new BrowserFake([
        Response::jwt(),
    ]);
    $ingestBrowser = new BrowserFake([
        new Response(duration: 2),
        new Response(duration: 2),
        //
        new Response(duration: 2),
        new Response(duration: 2),
        //
        new Response(duration: 2),
        new Response(duration: 2),
        //
        new Response(duration: 1),
        new Response(duration: 1),
        new Response(duration: 1),
        new Response(duration: 1),
    ]);
    $records = array_fill(0, 375_001, ['t' => 'request']);
    //
    $loop->addTimer(0, $server->pendingConnection($records));
    $loop->addTimer(0, $server->pendingConnection($records));
    //
    $loop->addTimer(3, $server->pendingConnection($records));
    $loop->addTimer(3, $server->pendingConnection($records));
    //
    $loop->addTimer(6, $server->pendingConnection($records));
    $loop->addTimer(6, $server->pendingConnection($records));
    //
    $loop->addTimer(9, $server->pendingConnection($records));
    $loop->addTimer(10, $server->pendingConnection($records));
    $loop->addTimer(11, $server->pendingConnection($records));
    $loop->addTimer(12, $server->pendingConnection($records));

    [$output, $e] = run(
        via: 'source',
        ingestDetailsBrowser: $ingestDetailsBrowser,
        ingestBrowser: $ingestBrowser,
        loop: $loop,
        server: $server,
        timeout: 10.0,
    );

    expect($e)->toBeNull($e?->getMessage() ?? '');
    expect($output)->toMatchLog(<<<'OUTPUT'
        {date} {info} Authentication successful {duration}
        {date} {info} Ingest successful {duration}
        {date} {info} Ingest successful {duration}
        {date} {info} Ingest successful {duration}
        {date} {info} Ingest successful {duration}
        {date} {info} Ingest successful {duration}
        {date} {info} Ingest successful {duration}
        {date} {info} Ingest successful {duration}
        {date} {info} Ingest successful {duration}
        {date} {info} Ingest successful {duration}
        {date} {info} Ingest successful {duration}
        OUTPUT);

    expect($ingestBrowser)->toHaveSent([
        Request::ingest($records),
        Request::ingest($records),
        Request::ingest($records),
        Request::ingest($records),
        Request::ingest($records),
        Request::ingest($records),
        Request::ingest($records),
        Request::ingest($records),
        Request::ingest($records),
        Request::ingest($records),
    ]);
    expect($ingestDetailsBrowser)->toBeProcessing([]);
    expect($ingestBrowser)->toHavePending([]);
    expect($loop)->toHaveRun([
        new Timer(interval: 0, runAt: 0, scheduledBy: self::class),
        new Timer(interval: 0, runAt: 0, scheduledBy: self::class),
        new Timer(interval: 2, runAt: 2, scheduledBy: 'Tests\Response::toPromise'),
        new Timer(interval: 2, runAt: 2, scheduledBy: 'Tests\Response::toPromise'),
        new Timer(interval: 3, runAt: 3, scheduledBy: self::class),
        new Timer(interval: 3, runAt: 3, scheduledBy: self::class),
        new Timer(interval: 2, runAt: 5, scheduledBy: 'Tests\Response::toPromise'),
        new Timer(interval: 2, runAt: 5, scheduledBy: 'Tests\Response::toPromise'),
        new Timer(interval: 6, runAt: 6, scheduledBy: self::class),
        new Timer(interval: 6, runAt: 6, scheduledBy: self::class),
        new Timer(interval: 2, runAt: 8, scheduledBy: 'Tests\Response::toPromise'),
        new Timer(interval: 2, runAt: 8, scheduledBy: 'Tests\Response::toPromise'),
        new Timer(interval: 9, runAt: 9, scheduledBy: self::class),
        new Timer(interval: 10, runAt: 10, scheduledBy: self::class),
        new Timer(interval: 1, runAt: 10, scheduledBy: 'Tests\Response::toPromise'),
        new Timer(interval: 11, runAt: 11, scheduledBy: self::class),
        new Timer(interval: 1, runAt: 11, scheduledBy: 'Tests\Response::toPromise'),
        new Timer(interval: 12, runAt: 12, scheduledBy: self::class),
        new Timer(interval: 1, runAt: 12, scheduledBy: 'Tests\Response::toPromise'),
        new Timer(interval: 1, runAt: 13, scheduledBy: 'Tests\Response::toPromise'),
    ]);
    expect($loop)->toHavePending([
        new Timer(interval: 3_600, runAt: 3_600, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
    ]);
    expect($ingestDetailsBrowser)->toHaveSent([
        Request::json('/api/agent-auth'),
    ]);
    expect($ingestDetailsBrowser)->toBeProcessing([]);
    expect($ingestDetailsBrowser)->toHavePending([]);
});
