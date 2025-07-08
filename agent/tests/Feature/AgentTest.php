<?php

namespace Tests\Feature;

use Tests\BrowserFake;
use Tests\LoopFake;
use Tests\Response;
use Tests\TestCase;
use Tests\Timer;

class AgentTest extends TestCase
{
    public function test_it_works(): void
    {
        // Start the agent
        // Change the signature on disk
        // See that a restart is scheduled for 5 minutes
        // See that it restarts after 5 minmutes
        $loop = new LoopFake(runForSeconds: 60 * 20);
        $ingestDetailsBrowser = new BrowserFake([Response::jwt()]);

        [$output, $e] = $this->runAgent(
            via: 'source',
            ingestDetailsBrowser: $ingestDetailsBrowser,
            loop: $loop,
        );

        $this->assertNull($e, $e?->getMessage() ?? '');
        $this->assertLogMatches(<<<'OUTPUT'
            {date} {info} Authentication successful {duration}
            {date} {info} Agent signature changed: restarting in 5 minutes
            {date} {info} Agent signature changed: restarting in 4 minutes
            {date} {info} Agent signature changed: restarting in 3 minutes
            {date} {info} Agent signature changed: restarting in 2 minutes
            {date} {info} Agent signature changed: restarting in 1 minutes
            {date} {info} Agent signature changed: restarting
            {date} {info} Authentication successful {duration}
            OUTPUT, $output);
        $loop->assertRun([
            // new Timer(interval: 1, runAt: 1, scheduledAt: 0, scheduledBy: $this->functionName()),
        ]);
        $loop->assertPending([
            // new Timer(interval: 10, runAt: 11, scheduledAt: 1, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
            // new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
        ]);
        $ingestDetailsBrowser->assertSent([
            Request::json('/api/agent-auth'),
        ]);
        $ingestDetailsBrowser->assertProcessing([]);
        $ingestDetailsBrowser->assertPending([]);
    }

    public function test_it_does_not_restart_unless_signature_changes(): void
    {
        // touch the file.
    }
}

