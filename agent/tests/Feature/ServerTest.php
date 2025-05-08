<?php

namespace Tests\Feature;

use Tests\BrowserFake;
use Tests\Connection;
use Tests\LoopFake;
use Tests\Request;
use Tests\Response;
use Tests\TcpServerFake;
use Tests\TestCase;
use Tests\Timer;

use function expect;
use function run;

class ServerTest extends TestCase
{
    public function test_it_responds_with_ok(): void
    {
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
        expect($output)->toMatchLog(<<<'OUTPUT'
        {date} {info} Authentication successful {duration}
        OUTPUT);
        expect($loop)->toHaveRun([
            new Timer(interval: 1, runAt: 1, scheduledAt: 0, scheduledBy: $this->functionName()),
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
    }

    public function test_it_can_be_pinged(): void
    {
        $loop = new LoopFake(runForSeconds: 1);
        $server = new TcpServerFake;
        $ingestDetailsBrowser = new BrowserFake([Response::jwt()]);
        $ingestBrowser = new BrowserFake;

        $loop->addTimer(0, $server->pendingConnection('4:PING'));

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
        expect($output)->toMatchLog(<<<'OUTPUT'
        {date} {info} Authentication successful {duration}
        OUTPUT);
        expect($loop)->toHaveRun([
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
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
    }
}
