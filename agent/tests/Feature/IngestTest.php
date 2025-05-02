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
        Response::ingest(),
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
        new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: self::class),
    ]);
    expect($loop)->toHavePending([
        new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
    ]);
    expect($ingestDetailsBrowser)->toHaveSent([
        Request::json('/api/agent-auth'),
    ]);
    expect($ingestDetailsBrowser)->toHavePending([]);
});

it('handles unsuccessful responses', function () {
    $loop = new LoopFake(runForSeconds: 11);
    $server = new TcpServerFake;
    $ingestDetailsBrowser = new BrowserFake([
        Response::jwt(),
    ]);
    $ingestBrowser = new BrowserFake([
        Response::internalServerError('Whoops!'),
    ]);
    $loop->addTimer(0, $server->pendingConnection([['t' => 'request']]));

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
    expect($ingestBrowser)->toHaveSent([
        Request::ingest([['t' => 'request']]),
    ]);
    expect($ingestBrowser)->toHavePending([]);
    expect($loop)->toHaveRun([
        new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: self::class),
        new Timer(interval: 10, runAt: 10, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
    ]);
    expect($loop)->toHavePending([
        new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
    ]);
    expect($ingestDetailsBrowser)->toHaveSent([
        Request::json('/api/agent-auth'),
    ]);
    expect($ingestDetailsBrowser)->toHavePending([]);
});

it('handles runtime exceptions while procesing the request', function () {
    $loop = new LoopFake(runForSeconds: 11);
    $server = new TcpServerFake;
    $ingestDetailsBrowser = new BrowserFake([
        Response::jwt(),
    ]);
    $ingestBrowser = new BrowserFake([
        Response::throwWhileProcessing('Whoops!'),
    ]);
    $loop->addTimer(0, $server->pendingConnection([['t' => 'request']]));

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
    expect($ingestBrowser)->toHaveSent([
        Request::ingest([['t' => 'request']]),
    ]);
    expect($ingestBrowser)->toHavePending([]);
    expect($loop)->toHaveRun([
        new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: self::class),
        new Timer(interval: 10, runAt: 10, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
    ]);
    expect($loop)->toHavePending([
        new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
    ]);
    expect($ingestDetailsBrowser)->toHaveSent([
        Request::json('/api/agent-auth'),
    ]);
    expect($ingestDetailsBrowser)->toHavePending([]);
});

it('handles missing authentication details', function () {
    $loop = new LoopFake(runForSeconds: 11);
    $server = new TcpServerFake;
    $ingestDetailsBrowser = new BrowserFake([
        Response::unauthenticated(),
    ]);
    $ingestBrowser = new BrowserFake([]);
    $loop->addTimer(0, $server->pendingConnection([['t' => 'request']]));

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
    expect($ingestBrowser)->toHaveSentNothing();
    expect($ingestBrowser)->toHavePending([]);
    expect($loop)->toHaveRun([
        new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: self::class),
        new Timer(interval: 10, runAt: 10, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
    ]);
    expect($loop)->toHavePending([
        new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
    ]);
    expect($ingestDetailsBrowser)->toHaveSent([
        Request::json('/api/agent-auth'),
    ]);
    expect($ingestDetailsBrowser)->toHavePending([]);
});

it('limits response body included in logs', function () {
    $loop = new LoopFake(runForSeconds: 22);
    $server = new TcpServerFake;
    $ingestDetailsBrowser = new BrowserFake([
        Response::jwt(),
    ]);
    $ingestBrowser = new BrowserFake([
        Response::internalServerError(str_repeat('a', 255)),
        Response::internalServerError(str_repeat('a', 256)),
    ]);
    $loop->addTimer(0, $server->pendingConnection([['t' => 'request']]));
    $loop->addTimer(11, $server->pendingConnection([['t' => 'request']]));

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
    expect($ingestBrowser)->toHaveSent([
        Request::ingest([['t' => 'request']]),
        Request::ingest([['t' => 'request']]),
    ]);
    expect($ingestBrowser)->toHavePending([]);
    expect($loop)->toHaveRun([
        new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: self::class),
        new Timer(interval: 10, runAt: 10, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
        new Timer(interval: 11, runAt: 11, scheduledAt: 0, scheduledBy: self::class),
        new Timer(interval: 10, runAt: 21, scheduledAt: 11, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
    ]);
    expect($loop)->toHavePending([
        new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
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
        Response::ingest(),
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
        : [Response::ingest()]);
    expect($loop)->toHaveRun([
        new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: self::class),
        ...($duration === 1
            ? [new Timer(interval: $duration, runAt: $duration, scheduledAt: 0, scheduledBy: 'Tests\Response::toPromise')]
            : []),
    ]);
    expect($loop)->toHavePending($duration === 1
        ? [new Timer(interval: 3_600, runAt: 3_601, scheduledAt: 1, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn')]
        : [new Timer(interval: $duration, runAt: $duration, scheduledAt: 0, scheduledBy: 'Tests\Response::toPromise')]);
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
    expect($ingestBrowser)->toHaveSentNothing();
    expect($ingestBrowser)->toHavePending([]);
    expect($loop)->toHaveRun([
        new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: self::class),
        new Timer(interval: 1, runAt: 1, scheduledAt: 0, scheduledBy: 'Tests\Response::toPromise'),
    ]);
    expect($loop)->toHavePending([
        new Timer(interval: 2.5, runAt: 3.5, scheduledAt: 1, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
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
    expect($ingestBrowser)->toHaveSentNothing();
    expect($ingestBrowser)->toHavePending([]);
    expect($loop)->toHaveRun([
        new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: self::class),
        new Timer(interval: 1, runAt: 1, scheduledAt: 0, scheduledBy: 'Tests\Response::toPromise'),
    ]);
    expect($loop)->toHavePending([
        new Timer(interval: 2.5, runAt: 3.5, scheduledAt: 1, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
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
        Response::ingest(duration: 3),
        Response::ingest(duration: 4),
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
        new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: self::class),
        new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: self::class),
        new Timer(interval: 3, runAt: 3, scheduledAt: 0, scheduledBy: 'Tests\Response::toPromise'),
        new Timer(interval: 4, runAt: 4, scheduledAt: 0, scheduledBy: 'Tests\Response::toPromise'),
    ]);
    expect($loop)->toHavePending([
        new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
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
        Response::ingest(duration: 3),
        Response::ingest(duration: 4),
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
        new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: self::class),
        new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: self::class),
        new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: self::class),
        new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: self::class),
        new Timer(interval: 3, runAt: 3, scheduledAt: 0, scheduledBy: 'Tests\Response::toPromise'),
        new Timer(interval: 4, runAt: 4, scheduledAt: 0, scheduledBy: 'Tests\Response::toPromise'),
    ]);
    expect($loop)->toHavePending([
        new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
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
        Response::ingest(duration: 2),
        Response::ingest(duration: 2),
        //
        Response::ingest(duration: 2),
        Response::ingest(duration: 2),
        //
        Response::ingest(duration: 2),
        Response::ingest(duration: 2),
        //
        Response::ingest(duration: 1),
        Response::ingest(duration: 1),
        Response::ingest(duration: 1),
        Response::ingest(duration: 1),
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
        new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: self::class),
        new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: self::class),
        new Timer(interval: 2, runAt: 2, scheduledAt: 0, scheduledBy: 'Tests\Response::toPromise'),
        new Timer(interval: 2, runAt: 2, scheduledAt: 0, scheduledBy: 'Tests\Response::toPromise'),
        new Timer(interval: 3, runAt: 3, scheduledAt: 0, scheduledBy: self::class),
        new Timer(interval: 3, runAt: 3, scheduledAt: 0, scheduledBy: self::class),
        new Timer(interval: 2, runAt: 5, scheduledAt: 3, scheduledBy: 'Tests\Response::toPromise'),
        new Timer(interval: 2, runAt: 5, scheduledAt: 3, scheduledBy: 'Tests\Response::toPromise'),
        new Timer(interval: 6, runAt: 6, scheduledAt: 0, scheduledBy: self::class),
        new Timer(interval: 6, runAt: 6, scheduledAt: 0, scheduledBy: self::class),
        new Timer(interval: 2, runAt: 8, scheduledAt: 6, scheduledBy: 'Tests\Response::toPromise'),
        new Timer(interval: 2, runAt: 8, scheduledAt: 6, scheduledBy: 'Tests\Response::toPromise'),
        new Timer(interval: 9, runAt: 9, scheduledAt: 0, scheduledBy: self::class),
        new Timer(interval: 10, runAt: 10, scheduledAt: 0, scheduledBy: self::class),
        new Timer(interval: 1, runAt: 10, scheduledAt: 9, scheduledBy: 'Tests\Response::toPromise'),
        new Timer(interval: 11, runAt: 11, scheduledAt: 0, scheduledBy: self::class),
        new Timer(interval: 1, runAt: 11, scheduledAt: 10, scheduledBy: 'Tests\Response::toPromise'),
        new Timer(interval: 12, runAt: 12, scheduledAt: 0, scheduledBy: self::class),
        new Timer(interval: 1, runAt: 12, scheduledAt: 11, scheduledBy: 'Tests\Response::toPromise'),
        new Timer(interval: 1, runAt: 13, scheduledAt: 12, scheduledBy: 'Tests\Response::toPromise'),
    ]);
    expect($loop)->toHavePending([
        new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
    ]);
    expect($ingestDetailsBrowser)->toHaveSent([
        Request::json('/api/agent-auth'),
    ]);
    expect($ingestDetailsBrowser)->toBeProcessing([]);
    expect($ingestDetailsBrowser)->toHavePending([]);
});

it('schedules an ingest when buffer is empty and a payload under the threshold is received', function () {
    $loop = new LoopFake(runForSeconds: 10);
    $server = new TcpServerFake;
    $ingestDetailsBrowser = new BrowserFake([
        Response::jwt(),
    ]);
    $ingestBrowser = new BrowserFake([]);
    $loop->addTimer(0, $server->pendingConnection([['t' => 'request']]));
    $loop->addTimer(1, $server->pendingConnection([['t' => 'request']]));
    $loop->addTimer(2, $server->pendingConnection([['t' => 'request']]));

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
        OUTPUT);
    expect($ingestBrowser)->toHaveSentNothing();
    expect($ingestBrowser)->toBeProcessing([]);
    expect($ingestBrowser)->toHavePending([]);
    expect($loop)->toHaveRun([
        new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: self::class),
        new Timer(interval: 1, runAt: 1, scheduledAt: 0, scheduledBy: self::class),
        new Timer(interval: 2, runAt: 2, scheduledAt: 0, scheduledBy: self::class),
    ]);
    expect($loop)->toHavePending([
        new Timer(interval: 10, runAt: 10, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
        new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
    ]);
    expect($ingestDetailsBrowser)->toHaveSent([
        Request::json('/api/agent-auth'),
    ]);
    expect($ingestDetailsBrowser)->toHavePending([]);
});

it('ingests payloads under the threshold after 10 seconds', function () {
    $loop = new LoopFake(runForSeconds: 11);
    $server = new TcpServerFake;
    $ingestDetailsBrowser = new BrowserFake([
        Response::jwt(),
    ]);
    $ingestBrowser = new BrowserFake([
        Response::ingest(),
    ]);
    $loop->addTimer(0, $server->pendingConnection([['t' => 'request']]));

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
    expect($ingestBrowser)->toHaveSent([
        Request::ingest([['t' => 'request']]),
    ]);
    expect($ingestBrowser)->toBeProcessing([]);
    expect($ingestBrowser)->toHavePending([]);
    expect($loop)->toHaveRun([
        new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: self::class),
        new Timer(interval: 10, runAt: 10, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
    ]);
    expect($loop)->toHavePending([
        new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
    ]);
    expect($ingestDetailsBrowser)->toHaveSent([
        Request::json('/api/agent-auth'),
    ]);
    expect($ingestDetailsBrowser)->toHavePending([]);
});

it('ingests payloads before 10 seconds if the buffer exceeds the threshold', function () {
    $loop = new LoopFake(runForSeconds: 11);
    $server = new TcpServerFake;
    $ingestDetailsBrowser = new BrowserFake([
        Response::jwt(),
    ]);
    $ingestBrowser = new BrowserFake([
        Response::ingest(),
    ]);
    $loop->addTimer(0, $server->pendingConnection([['t' => 'request']]));
    $loop->addTimer(1, $server->pendingConnection([['t' => 'request']]));
    $loop->addTimer(2, $server->pendingConnection([['t' => 'request']]));
    $records = array_fill(0, 375_001, ['t' => 'request']);
    $loop->addTimer(3, $server->pendingConnection($records));

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
    expect($ingestBrowser)->toHaveSent([
        Request::ingest([
            ['t' => 'request'],
            ['t' => 'request'],
            ['t' => 'request'],
            ...$records,
        ]),
    ]);
    expect($ingestBrowser)->toBeProcessing([]);
    expect($ingestBrowser)->toHavePending([]);
    expect($loop)->toHaveRun([
        new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: self::class),
        new Timer(interval: 1, runAt: 1, scheduledAt: 0, scheduledBy: self::class),
        new Timer(interval: 2, runAt: 2, scheduledAt: 0, scheduledBy: self::class),
        new Timer(interval: 3, runAt: 3, scheduledAt: 0, scheduledBy: self::class),
    ]);
    expect($loop)->toHaveCanceled([
        new Timer(interval: 10, canceledAt: 3, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
    ]);
    expect($loop)->toHavePending([
        new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
    ]);
    expect($ingestDetailsBrowser)->toHaveSent([
        Request::json('/api/agent-auth'),
    ]);
    expect($ingestDetailsBrowser)->toHavePending([]);
});

it('ingests immediately when buffer is empty and a payload over the threshold is received', function () {
    $loop = new LoopFake(runForSeconds: 1);
    $server = new TcpServerFake;
    $ingestDetailsBrowser = new BrowserFake([
        Response::jwt(),
    ]);
    $ingestBrowser = new BrowserFake([
        Response::ingest(),
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
    expect($ingestBrowser)->toHaveSent([
        Request::ingest($records),
    ]);
    expect($ingestBrowser)->toBeProcessing([]);
    expect($ingestBrowser)->toHavePending([]);
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
});

it('stops ingesting data when over quota', function () {
    $loop = new LoopFake(runForSeconds: 60);
    $server = new TcpServerFake;
    $ingestDetailsBrowser = new BrowserFake([
        Response::jwt(),
    ]);
    $ingestBrowser = new BrowserFake([
        Response::ingest(remaining: 1),
        Response::ingest(remaining: 0),
    ]);
    $loop->addTimer(0, $server->pendingConnection([['t' => 'request']]));
    $loop->addTimer(11, $server->pendingConnection([['t' => 'request']]));
    $loop->addTimer(22, $server->pendingConnection([['t' => 'request']]));

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
        {date} {info} Ingest attempted {duration}: Quota exceeded
        OUTPUT);
    expect($ingestBrowser)->toHaveSent([
        Request::ingest([['t' => 'request']]),
        Request::ingest([['t' => 'request']]),
    ]);
    expect($ingestBrowser)->toHavePending([]);
    expect($loop)->toHaveRun([
        new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: self::class),
        new Timer(interval: 10, runAt: 10, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
        new Timer(interval: 11, runAt: 11, scheduledAt: 0, scheduledBy: self::class),
        new Timer(interval: 10, runAt: 21, scheduledAt: 11, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
        new Timer(interval: 22, runAt: 22, scheduledAt: 0, scheduledBy: self::class),
    ]);
    expect($loop)->toHaveCanceled([
        new Timer(interval: 3_600, canceledAt: 21, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
    ]);
    expect($loop)->toHavePending([
        new Timer(interval: 900, runAt: 921, scheduledAt: 21, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
    ]);
    expect($ingestDetailsBrowser)->toHaveSent([
        Request::json('/api/agent-auth'),
    ]);
    expect($ingestDetailsBrowser)->toHavePending([]);
});

it('starts ingesting data after a subsequent successful authentication', function () {
    $loop = new LoopFake(runForSeconds: 933);
    $server = new TcpServerFake;
    $ingestDetailsBrowser = new BrowserFake([
        Response::jwt(),
        Response::jwt(),
    ]);
    $ingestBrowser = new BrowserFake([
        Response::ingest(remaining: 1),
        Response::ingest(remaining: 0),
        Response::ingest(remaining: 0),
    ]);
    $loop->addTimer(0, $server->pendingConnection([['t' => 'request 1']]));
    $loop->addTimer(11, $server->pendingConnection([['t' => 'request 2']]));
    $loop->addTimer(22, $server->pendingConnection([['t' => 'request 3']]));
    $loop->addTimer(922, $server->pendingConnection([['t' => 'request 4']]));

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
        {date} {info} Ingest attempted {duration}: Quota exceeded
        {date} {info} Authentication successful {duration}
        {date} {info} Ingest attempted {duration}: Quota exceeded
        OUTPUT);
    expect($ingestBrowser)->toHaveSent([
        Request::ingest([['t' => 'request 1']]),
        Request::ingest([['t' => 'request 2']]),
        Request::ingest([['t' => 'request 4']]),
    ]);
    expect($ingestBrowser)->toHavePending([]);
    expect($loop)->toHaveRun([
        new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: self::class),
        new Timer(interval: 10, runAt: 10, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
        new Timer(interval: 11, runAt: 11, scheduledAt: 0, scheduledBy: self::class),
        new Timer(interval: 10, runAt: 21, scheduledAt: 11, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
        new Timer(interval: 22, runAt: 22, scheduledAt: 0, scheduledBy: self::class),
        new Timer(interval: 900, runAt: 921, scheduledAt: 21, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
        new Timer(interval: 922, runAt: 922, scheduledAt: 0, scheduledBy: self::class),
        new Timer(interval: 10, runAt: 932, scheduledAt: 922, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
    ]);
    expect($loop)->toHaveCanceled([
        new Timer(interval: 3_600, canceledAt: 21, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
        new Timer(interval: 3_600, canceledAt: 932, scheduledAt: 921, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
    ]);
    expect($loop)->toHavePending([
        new Timer(interval: 900, runAt: 900 + 932, scheduledAt: 932, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
    ]);
    expect($ingestDetailsBrowser)->toHaveSent([
        Request::json('/api/agent-auth'),
        Request::json('/api/agent-auth'),
    ]);
    expect($ingestDetailsBrowser)->toHavePending([]);
});

it('handles incomplete payloads', function () {
    $loop = new LoopFake(runForSeconds: 6);
    $server = new TcpServerFake;
    $ingestDetailsBrowser = new BrowserFake([
        Response::jwt(),
    ]);
    $ingestBrowser = new BrowserFake([]);
    $loop->addTimer(0, $server->pendingConnection('4'));
    $loop->addTimer(1, $server->pendingConnection('4:'));
    $loop->addTimer(2, $server->pendingConnection('4:['));
    $loop->addTimer(3, $server->pendingConnection('4:[{'));
    $loop->addTimer(4, $server->pendingConnection('4:[{}'));
    $loop->addTimer(5, $server->pendingConnection('4:[{}]'));

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
        {date} {error} Connection error: Incomplete payload received\. Length: \[\] Value: \[4\]
        {date} {error} Connection error: Incomplete payload received\. Length: \[4\] Value: \[\]
        {date} {error} Connection error: Incomplete payload received\. Length: \[4\] Value: \[\[\]
        {date} {error} Connection error: Incomplete payload received\. Length: \[4\] Value: \[\[\{\]
        {date} {error} Connection error: Incomplete payload received\. Length: \[4\] Value: \[\[\{\}\]
        OUTPUT);
    expect($loop)->toHaveRun([
        new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: self::class),
        new Timer(interval: 1, runAt: 1, scheduledAt: 0, scheduledBy: self::class),
        new Timer(interval: 2, runAt: 2, scheduledAt: 0, scheduledBy: self::class),
        new Timer(interval: 3, runAt: 3, scheduledAt: 0, scheduledBy: self::class),
        new Timer(interval: 4, runAt: 4, scheduledAt: 0, scheduledBy: self::class),
        new Timer(interval: 5, runAt: 5, scheduledAt: 0, scheduledBy: self::class),
    ]);
    expect($loop)->toHavePending([
        new Timer(interval: 10, runAt: 15, scheduledAt: 5, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
        new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
    ]);
    expect($ingestDetailsBrowser)->toHaveSent([
        Request::json('/api/agent-auth'),
    ]);
});
