<?php

use Tests\BrowserFake;
use Tests\LoopFake;
use Tests\Request;
use Tests\Response;
use Tests\TcpServerFake;
use Tests\Timer;

it('works', function () {
    $loop = new LoopFake(runForSeconds: 1);
    $server = new TcpServerFake;
    $browser = new BrowserFake([
        Response::jwt(),
    ]);
    $loop->addTimer(0, $server->pendingConnection('asdf'));

    [$output, $e] = run(
        via: 'source',
        ingestDetailsBrowser: $browser,
        loop: $loop,
        server: $server,
    );

    echo $output;

    expect($e)->toBeNull($e?->getMessage() ?? '');
    expect($browser)->toHaveSent([
        Request::json('/api/agent-auth'),
    ]);
    expect($browser)->toHavePending([]);
    expect($output)->toMatchLog(<<<'OUTPUT'
        {date} {info} Authentication successful {duration}
        OUTPUT);
    expect($loop)->toHaveRun([]);
    $scheduleRefreshIn = 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn';
    expect($loop)->toHavePending([
        new Timer(interval: 1, runAt: 1, scheduledBy: $scheduleRefreshIn),
        new Timer(interval: 3_600, runAt: 3_600, scheduledBy: $scheduleRefreshIn),
    ]);
});
