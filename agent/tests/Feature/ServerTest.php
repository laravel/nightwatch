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

class ServerTest extends TestCase
{
    public function test_it_responds_with_ok(): void
    {
        $loop = new LoopFake(runForSeconds: 2);
        $server = new TcpServerFake;
        $ingestDetailsBrowser = new BrowserFake([Response::jwt()]);
        $ingestBrowser = new BrowserFake;

        $loop->addTimer(1, $server->pendingConnection([['t' => 'request']]));

        [$output, $e] = $this->runAgent(
            via: 'source',
            ingestDetailsBrowser: $ingestDetailsBrowser,
            ingestBrowser: $ingestBrowser,
            loop: $loop,
            server: $server,
        );

        $this->assertNull($e, $e?->getMessage() ?? '');
        $server->assertHandled([
            Connection::ok(),
        ]);
        $server->assertOpen();
        $this->assertLogMatches(<<<'OUTPUT'
            {date} {info} Authentication successful {duration}
            OUTPUT, $output);
        $loop->assertRun([
            new Timer(interval: 1, runAt: 1, scheduledAt: 0, scheduledBy: $this->functionName()),
        ]);
        $loop->assertPending([
            new Timer(interval: 10, runAt: 11, scheduledAt: 1, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
            new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
        ]);
        $ingestDetailsBrowser->assertSent([
            Request::json('/api/agent-auth'),
        ]);
        $ingestDetailsBrowser->assertPending([]);
        $ingestBrowser->assertSent([]);
        $ingestBrowser->assertPending([]);
    }

    public function test_it_can_be_pinged(): void
    {
        $tokenHash = self::tokenHash();

        $loop = new LoopFake(runForSeconds: 1);
        $server = new TcpServerFake;
        $ingestDetailsBrowser = new BrowserFake([Response::jwt()]);
        $ingestBrowser = new BrowserFake;

        $loop->addTimer(0, $server->pendingConnection('15:v1:'.$tokenHash.':PING'));

        [$output, $e] = $this->runAgent(
            via: 'source',
            ingestDetailsBrowser: $ingestDetailsBrowser,
            ingestBrowser: $ingestBrowser,
            loop: $loop,
            server: $server,
        );

        $this->assertNull($e, $e?->getMessage() ?? '');
        $server->assertHandled([
            Connection::ok(),
        ]);
        $server->assertOpen();
        $this->assertLogMatches(<<<'OUTPUT'
            {date} {info} Authentication successful {duration}
            OUTPUT, $output);
        $loop->assertRun([
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
        ]);
        $loop->assertPending([
            new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
        ]);
        $ingestDetailsBrowser->assertSent([
            Request::json('/api/agent-auth'),
        ]);
        $ingestDetailsBrowser->assertPending([]);
        $ingestBrowser->assertSent([]);
        $ingestBrowser->assertPending([]);
    }

    public function test_it_stops_loop_when_an_incorrect_payload_version_is_received(): void
    {
        $tokenHash = self::tokenHash();

        $loop = new LoopFake(runForSeconds: 2);
        $server = new TcpServerFake;
        $ingestDetailsBrowser = new BrowserFake([Response::jwt()]);
        $ingestBrowser = new BrowserFake([]);

        $loop->addTimer(1, $server->pendingConnection('20:INVALID:'.$tokenHash.':[{}]'));

        [$output, $e] = $this->runAgent(
            via: 'source',
            ingestDetailsBrowser: $ingestDetailsBrowser,
            ingestBrowser: $ingestBrowser,
            loop: $loop,
            server: $server,
        );

        $this->assertNull($e, $e?->getMessage() ?? '');
        $server->assertHandled([
            Connection::ok(),
        ]);
        $server->assertClosed();
        $this->assertLogMatches(<<<'OUTPUT'
        {date} {info} Authentication successful {duration}
        {date} {info} Incoming payload version has changed
        {date} {info} Shutting down
        OUTPUT, $output);
        $loop->assertRun([
            new Timer(interval: 1, runAt: 1, scheduledAt: 0, scheduledBy: $this->functionName()),
        ]);
        $loop->assertPending([
            new Timer(interval: 3_600, runAt: null, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
        ]);
        $this->assertTrue($loop->stopped);
        $ingestDetailsBrowser->assertSent([
            Request::json('/api/agent-auth'),
        ]);
        $ingestDetailsBrowser->assertPending([]);
        $ingestBrowser->assertSent([]);
        $ingestBrowser->assertPending([]);
    }

    public function test_it_errors_when_an_incorrect_token_hash_is_received(): void
    {
        $tokenHash = self::tokenHash();
        $loop = new LoopFake(runForSeconds: 40);
        $server = new TcpServerFake;
        $ingestDetailsBrowser = new BrowserFake([Response::jwt()]);
        $ingestBrowser = new BrowserFake([
            Response::ingested(),
        ]);

        $loop->addTimer(1, $server->pendingConnection('15:v1:INVALID:[{}]'));
        $loop->addTimer(20, $server->pendingConnection([['t' => 'request']]));

        [$output, $e] = $this->runAgent(
            via: 'source',
            ingestDetailsBrowser: $ingestDetailsBrowser,
            ingestBrowser: $ingestBrowser,
            loop: $loop,
            server: $server,
        );

        $this->assertNull($e, $e?->getMessage() ?? '');
        $server->assertOpen();
        $server->assertHandled([
            Connection::ok(),
            Connection::ok(),
        ]);
        $this->assertLogMatches(<<<'OUTPUT'
        {date} {info} Authentication successful {duration}
        {date} {error} Incoming token hash mismatch! Check your application/agent configuration.
        {date} {info} Ingest successful {duration}
        OUTPUT, $output);
        $loop->assertRun([
            new Timer(interval: 1, runAt: 1, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 20, runAt: 20, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 10, runAt: 30, scheduledAt: 20, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
        ]);
        $loop->assertPending([
            new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
        ]);
        $this->assertFalse($loop->stopped);
        $ingestDetailsBrowser->assertSent([
            Request::json('/api/agent-auth'),
        ]);
        $ingestBrowser->assertSent([
            Request::ingest([['t' => 'request']]),
        ]);
        $ingestDetailsBrowser->assertPending([]);
        $ingestBrowser->assertPending([]);
    }
}
