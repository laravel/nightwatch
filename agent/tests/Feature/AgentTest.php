<?php

namespace Tests\Feature;

use Tests\BrowserFake;
use Tests\LoopFake;
use Tests\Request;
use Tests\Response;
use Tests\TestCase;
use Tests\Timer;

use function file_get_contents;
use function file_put_contents;

class AgentTest extends TestCase
{
    public static function write_signature(string $content = 'abcd'): void
    {
        file_put_contents(__DIR__.'/../../build/signature.txt', $content);
    }

    public static function get_signature(): string
    {
        return file_get_contents(__DIR__.'/../../build/signature.txt') ?: '';
    }

    public static function touch_signature(): void
    {
        $signature = self::get_signature();
        self::write_signature($signature);
    }

    public function test_it_works(): void
    {
        $originalSignature = self::get_signature();
        try {
            $loop = new LoopFake(runForSeconds: 60 * 20);
            $loop->addTimer(1, [self::class, 'write_signature']);
            $ingestDetailsBrowser = new BrowserFake([Response::jwt()]);

            [$output, $e] = $this->runAgent(
                via: 'source',
                ingestDetailsBrowser: $ingestDetailsBrowser,
                loop: $loop,
            );

            $this->assertNull($e, $e?->getMessage() ?? '');
            $loop->assertCanceled([
                new Timer(interval: 60, canceledAt: 60, scheduledAt: 0, scheduledBy: 'Agent'), // signature check
                new Timer(interval: 60, canceledAt: 360, scheduledAt: 60, scheduledBy: 'Agent'), // app shutdown
            ]);
            $this->assertLogMatches(<<<'OUTPUT'
                {date} {info} Authentication successful {duration}
                {date} {info} Agent signature changed: shutting down in 5 minutes
                {date} {info} Agent signature changed: shutting down in 4 minutes
                {date} {info} Agent signature changed: shutting down in 3 minutes
                {date} {info} Agent signature changed: shutting down in 2 minutes
                {date} {info} Agent signature changed: shutting down in 1 minutes
                {date} {info} Shutting down
                OUTPUT, $output);

            $loop->assertRunWithPeriodic([
                new Timer(interval: 1, runAt: 1, scheduledAt: 0, scheduledBy: $this->functionName()),
                new Timer(interval: 60, runAt: 60, scheduledAt: 0, scheduledBy: 'Agent', periodic: true), // signature check: (see signature changed)
                new Timer(interval: 60, runAt: 120, scheduledAt: 60, scheduledBy: 'Agent', periodic: true), // app shutdown: 4 mins left
                new Timer(interval: 60, runAt: 180, scheduledAt: 60, scheduledBy: 'Agent', periodic: true), // app shutdown: 3 mins left
                new Timer(interval: 60, runAt: 240, scheduledAt: 60, scheduledBy: 'Agent', periodic: true), // app shutdown: 2 mins left
                new Timer(interval: 60, runAt: 300, scheduledAt: 60, scheduledBy: 'Agent', periodic: true), // app shutdown: 1 min left
                new Timer(interval: 60, runAt: 360, scheduledAt: 60, scheduledBy: 'Agent', periodic: true), // app shutdown: 0 min left
                new Timer(interval: 300, runAt: 360, scheduledAt: 60, scheduledBy: 'Agent'), // Signature mismatch shutdown
            ]);

            $loop->assertPendingWithPeriodic([
                new Timer(interval: 3_600, runAt: null, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
            ]);
            $ingestDetailsBrowser->assertSent([
                Request::json('/api/agent-auth'),
            ]);
            $ingestDetailsBrowser->assertProcessing([]);
            $ingestDetailsBrowser->assertPending([]);

            // make sure the agent comes back up correctly with the new signature
            $loop = new LoopFake(runForSeconds: 60 * 2 + 1);
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

            $loop->assertRunWithPeriodic([
                new Timer(interval: 60, runAt: 60, scheduledAt: 0, scheduledBy: 'Agent', periodic: true),
                new Timer(interval: 60, runAt: 120, scheduledAt: 0, scheduledBy: 'Agent', periodic: true),
            ]);
            $loop->assertPendingWithPeriodic([
                new Timer(interval: 60, runAt: 180, scheduledAt: 0, scheduledBy: 'Agent', periodic: true),
                new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
            ]);
            $ingestDetailsBrowser->assertSent([
                Request::json('/api/agent-auth'),
            ]);
            $ingestDetailsBrowser->assertProcessing([]);
            $ingestDetailsBrowser->assertPending([]);
        } finally {
            self::write_signature($originalSignature);
        }
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
