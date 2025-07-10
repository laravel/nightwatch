<?php

namespace Tests\Feature;

use Tests\BrowserFake;
use Tests\LoopFake;
use Tests\Request;
use Tests\Response;
use Tests\TestCase;
use Tests\Timer;

use function file_put_contents;

class AgentTest extends TestCase
{
    public static function write_signature(): void
    {
        file_put_contents(__DIR__.'/../../build/signature.txt', 'abcd');
    }

    public static function touch_signature(): void
    {
        file_put_contents(__DIR__.'/../../build/signature.txt', 'C8DB9D7EE8A6DB35E416926A3FDE38AA5913E0637CB7D833DA0ABA38E5C1A1F7E6FB927AC4CA67C658BC6B5ED819DDBD99B1C9B29A7B74E10D138D73FFAA03FC');
    }

    public function test_it_works(): void
    {
        // Start the agent
        // Change the signature on disk
        // See that a restart is scheduled for 5 minutes
        // See that it restarts after 5 minmutes
        $loop = new LoopFake(runForSeconds: 60 * 20);
        $loop->addTimer(1, [self::class, 'write_signature']);
        $ingestDetailsBrowser = new BrowserFake([Response::jwt()]);

        [$output, $e] = $this->runAgent(
            via: 'source',
            ingestDetailsBrowser: $ingestDetailsBrowser,
            loop: $loop,
        );

        self::touch_signature();

        $this->assertNull($e, $e?->getMessage() ?? '');
        $loop->assertCanceled([
            new Timer(interval: 60, canceledAt: 360, scheduledAt: 0, scheduledBy: 'Agent'),
        ]);
        $this->assertLogMatches(<<<'OUTPUT'
            {date} {info} Authentication successful {duration}
            {date} {info} Agent signature changed: restarting in 5 minutes
            {date} {info} Agent signature changed: restarting in 4 minutes
            {date} {info} Agent signature changed: restarting in 3 minutes
            {date} {info} Agent signature changed: restarting in 2 minutes
            {date} {info} Agent signature changed: restarting in 1 minutes
            {date} {info} Agent signature changed: restarting
            {date} {info} Shutting down
            OUTPUT, $output);

        $loop->assertRunWithPeriodic([
            new Timer(interval: 1, runAt: 1, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 60, runAt: 60, scheduledAt: 0, scheduledBy: 'Agent', periodic: true), // see signature changed
            new Timer(interval: 60, runAt: 120, scheduledAt: 0, scheduledBy: 'Agent', periodic: true), // 4 mins left
            new Timer(interval: 60, runAt: 180, scheduledAt: 0, scheduledBy: 'Agent', periodic: true), // 3 mins left
            new Timer(interval: 60, runAt: 240, scheduledAt: 0, scheduledBy: 'Agent', periodic: true), // 2 mins left
            new Timer(interval: 60, runAt: 300, scheduledAt: 0, scheduledBy: 'Agent', periodic: true), // 1 min left
            new Timer(interval: 60, runAt: 360, scheduledAt: 0, scheduledBy: 'Agent', periodic: true), // shutting down
            new Timer(interval: 300, runAt: 360, scheduledAt: 60, scheduledBy: '::Laravel\NightwatchAgent\{closure}'),
        ]);

        $loop->assertPendingWithPeriodic([
            new Timer(interval: 3_600, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
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
        $loop = new LoopFake(runForSeconds: 60 * 20);
        $loop->addTimer(1, [self::class, 'touch_signature']);
        $ingestDetailsBrowser = new BrowserFake([Response::jwt()]);

        [$output, $e] = $this->runAgent(
            via: 'source',
            ingestDetailsBrowser: $ingestDetailsBrowser,
            loop: $loop,
        );

        $this->assertNull($e, $e?->getMessage() ?? '');
        $this->assertLogMatches(<<<'OUTPUT'
            {date} {info} Authentication successful {duration}
            OUTPUT, $output);

    }
}
