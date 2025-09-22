<?php

namespace Tests\Feature;

use Tests\BrowserFake;
use Tests\LoopFake;
use Tests\Request;
use Tests\Response;
use Tests\TestCase;
use Tests\Timer;

use function file_put_contents;
use function unlink;

class AgentTest extends TestCase
{
    public function test_it_restarts_on_signature_changed(): void
    {
        $originalSignature = self::getSignature();
        try {
            $loop = new LoopFake(runForSeconds: 60 * 20);
            $loop->addTimer(1, [self::class, 'writeSignature']);
            $ingestDetailsBrowser = new BrowserFake([Response::jwt()]);

            [$output, $e] = $this->runAgent(
                via: 'source',
                ingestDetailsBrowser: $ingestDetailsBrowser,
                loop: $loop,
            );

            $this->assertNull($e, $e?->getMessage() ?? '');
            $loop->assertCanceled([
                new Timer(interval: 60, canceledAt: 60, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\CheckSignature::start'), // signature check
                new Timer(interval: 60, canceledAt: 360, scheduledAt: 60, scheduledBy: 'Laravel\NightwatchAgent\CheckSignature::scheduledShutdown'), // app shutdown
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
                new Timer(interval: 60, runAt: 60, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\CheckSignature::start', periodic: true), // signature check: (see signature changed)
                new Timer(interval: 60, runAt: 120, scheduledAt: 60, scheduledBy: 'Laravel\NightwatchAgent\CheckSignature::scheduledShutdown', periodic: true), // agent shutdown: 4 mins left
                new Timer(interval: 60, runAt: 180, scheduledAt: 60, scheduledBy: 'Laravel\NightwatchAgent\CheckSignature::scheduledShutdown', periodic: true), // agent shutdown: 3 mins left
                new Timer(interval: 60, runAt: 240, scheduledAt: 60, scheduledBy: 'Laravel\NightwatchAgent\CheckSignature::scheduledShutdown', periodic: true), // agent shutdown: 2 mins left
                new Timer(interval: 60, runAt: 300, scheduledAt: 60, scheduledBy: 'Laravel\NightwatchAgent\CheckSignature::scheduledShutdown', periodic: true), // agent shutdown: 1 min left
                new Timer(interval: 60, runAt: 360, scheduledAt: 60, scheduledBy: 'Laravel\NightwatchAgent\CheckSignature::scheduledShutdown', periodic: true), // agent shutdown
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
                new Timer(interval: 60, runAt: 60, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\CheckSignature::start', periodic: true),
                new Timer(interval: 60, runAt: 120, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\CheckSignature::start', periodic: true),
            ]);
            $loop->assertPendingWithPeriodic([
                new Timer(interval: 60, runAt: 180, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\CheckSignature::start', periodic: true),
                new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
            ]);
            $ingestDetailsBrowser->assertSent([
                Request::json('/api/agent-auth'),
            ]);
            $ingestDetailsBrowser->assertProcessing([]);
            $ingestDetailsBrowser->assertPending([]);
        } finally {
            self::writeSignature($originalSignature);
        }
    }

    public function test_it_outputs_debug_message_when_signature_changed(): void
    {
        $originalSignature = self::getSignature();
        try {
            $loop = new LoopFake(runForSeconds: 61);
            $loop->addTimer(1, [self::class, 'writeSignature']);
            $ingestDetailsBrowser = new BrowserFake([Response::jwt()]);

            [$output, $e] = $this->runAgent(
                via: 'source',
                ingestDetailsBrowser: $ingestDetailsBrowser,
                loop: $loop,
                verbose: true,
            );

            self::writeSignature($originalSignature);

            $this->assertNull($e, $e?->getMessage() ?? '');
            $this->assertLogMatches(<<<'OUTPUT'
                {date} {info} Authentication successful {duration}
                {date} {debug} Signature checked: \[abcd\]
                {date} {info} Agent signature changed: shutting down in 5 minutes
                OUTPUT, $output, verbose: true);
        } finally {
            self::writeSignature($originalSignature);
        }
    }

    public function test_it_does_not_restart_unless_signature_changes(): void
    {
        $loop = new LoopFake(runForSeconds: 60 * 20);
        $loop->addTimer(1, [self::class, 'touchSignature']);
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

    public function test_it_does_not_restart_if_the_signature_file_is_unaccessible_to_support_envoyer_symlink_deployments(): void
    {
        $originalSignature = self::getSignature();

        try {
            $loop = new LoopFake(runForSeconds: 121);
            $loop->addTimer(1, [self::class, 'unlinkSignature']);
            $loop->addTimer(61, [self::class, 'writeSignature']);
            $ingestDetailsBrowser = new BrowserFake([Response::jwt()]);

            [$output, $e] = $this->runAgent(
                via: 'source',
                ingestDetailsBrowser: $ingestDetailsBrowser,
                loop: $loop,
                verbose: true,
            );

            self::writeSignature($originalSignature);

            $this->assertNull($e, $e?->getMessage() ?? '');
            $this->assertLogMatches(<<<'OUTPUT'
                {date} {info} Authentication successful {duration}
                {date} {debug} Signature checked: \[\]
                {date} {debug} Signature checked: \[abcd\]
                {date} {info} Agent signature changed: shutting down in 5 minutes
                OUTPUT, $output, verbose: true);
        } finally {
            self::writeSignature($originalSignature);
        }
    }

    public static function writeSignature(string $content = "abcd\n"): void
    {
        file_put_contents(self::signaturePath(), $content);
    }

    public static function touchSignature(): void
    {
        $signature = self::getSignature();
        self::writeSignature($signature);
    }

    public static function unlinkSignature(): void
    {
        unlink(self::signaturePath());
    }
}
