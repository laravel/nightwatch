<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\BrowserFake;
use Tests\Connection;
use Tests\LoopFake;
use Tests\Request;
use Tests\Response;
use Tests\TcpServerFake;
use Tests\TestCase;
use Tests\Timer;

use function array_fill;
use function gethostname;
use function str_repeat;
use function substr;

class IngestTest extends TestCase
{
    public function test_it_can_ingests_records(): void
    {
        $loop = new LoopFake(runForSeconds: 1);
        $server = new TcpServerFake;
        $ingestDetailsBrowser = new BrowserFake([
            Response::jwt(),
        ]);
        $ingestBrowser = new BrowserFake([
            Response::ingested(),
        ]);
        $records = array_fill(0, 375_001, ['t' => 'request']);
        $loop->addTimer(0, $server->pendingConnection($records));

        [$output, $e] = $this->runAgent(
            via: 'source',
            ingestDetailsBrowser: $ingestDetailsBrowser,
            ingestBrowser: $ingestBrowser,
            loop: $loop,
            server: $server,
        );

        $this->assertNull($e, $e?->getMessage() ?? '');
        $this->assertLogMatches(<<<'OUTPUT'
        {date} {info} Authentication successful {duration}
        {date} {info} Ingest successful {duration}
        OUTPUT, $output);
        $this->assertSame(10.0, $ingestBrowser->timeout);
        $this->assertSame(5.0, $ingestBrowser->connectionTimeout);
        $this->assertNull($ingestBrowser->baseUrl);
        $this->assertSame([
            'accept' => 'application/json',
            'content-encoding' => 'gzip',
            'content-type' => 'application/json',
            'nightwatch-server' => gethostname(),
        ], $ingestBrowser->headers);
        $ingestBrowser->assertSent([
            Request::ingest($records),
        ]);
        $ingestBrowser->assertPending([]);
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
    }

    public function test_it_handles_unsuccessful_responses(): void
    {
        $loop = new LoopFake(runForSeconds: 11);
        $server = new TcpServerFake;
        $ingestDetailsBrowser = new BrowserFake([
            Response::jwt(),
        ]);
        $ingestBrowser = new BrowserFake([
            Response::internalServerError('Whoops!'),
        ]);
        $loop->addTimer(0, $server->pendingConnection([['t' => 'request']]));

        [$output, $e] = $this->runAgent(
            via: 'source',
            ingestDetailsBrowser: $ingestDetailsBrowser,
            ingestBrowser: $ingestBrowser,
            loop: $loop,
            server: $server,
        );

        $this->assertNull($e, $e?->getMessage() ?? '');
        $this->assertLogMatches(<<<'OUTPUT'
        {date} {info} Authentication successful {duration}
        {date} {info} Ingest failed {duration}: 500 \[Whoops!\]
        OUTPUT, $output);
        $ingestBrowser->assertSent([
            Request::ingest([['t' => 'request']]),
        ]);
        $ingestBrowser->assertPending([]);
        $loop->assertRun([
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 10, runAt: 10, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
        ]);
        $loop->assertPending([
            new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
        ]);
        $ingestDetailsBrowser->assertSent([
            Request::json('/api/agent-auth'),
        ]);
        $ingestDetailsBrowser->assertPending([]);
    }

    public function test_it_handles_runtime_exceptions_while_procesing_the_request(): void
    {
        $loop = new LoopFake(runForSeconds: 11);
        $server = new TcpServerFake;
        $ingestDetailsBrowser = new BrowserFake([
            Response::jwt(),
        ]);
        $ingestBrowser = new BrowserFake([
            Response::throwWhileProcessing('Whoops!'),
        ]);
        $loop->addTimer(0, $server->pendingConnection([['t' => 'request']]));

        [$output, $e] = $this->runAgent(
            via: 'source',
            ingestDetailsBrowser: $ingestDetailsBrowser,
            ingestBrowser: $ingestBrowser,
            loop: $loop,
            server: $server,
        );

        $this->assertNull($e, $e?->getMessage() ?? '');
        $this->assertLogMatches(<<<'OUTPUT'
        {date} {info} Authentication successful {duration}
        {date} {info} Ingest failed {duration}: Whoops!
        OUTPUT, $output);
        $ingestBrowser->assertSent([
            Request::ingest([['t' => 'request']]),
        ]);
        $ingestBrowser->assertPending([]);
        $loop->assertRun([
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 10, runAt: 10, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
        ]);
        $loop->assertPending([
            new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
        ]);
        $ingestDetailsBrowser->assertSent([
            Request::json('/api/agent-auth'),
        ]);
        $ingestDetailsBrowser->assertPending([]);
    }

    public function test_it_handles_missing_authentication_details(): void
    {
        $loop = new LoopFake(runForSeconds: 11);
        $server = new TcpServerFake;
        $ingestDetailsBrowser = new BrowserFake([
            Response::unauthenticated(),
        ]);
        $ingestBrowser = new BrowserFake([]);
        $loop->addTimer(0, $server->pendingConnection([['t' => 'request']]));

        [$output, $e] = $this->runAgent(
            via: 'source',
            ingestDetailsBrowser: $ingestDetailsBrowser,
            ingestBrowser: $ingestBrowser,
            loop: $loop,
            server: $server,
        );

        $this->assertNull($e, $e?->getMessage() ?? '');
        $this->assertLogMatches(<<<'OUTPUT'
        {date} {info} Authentication failed {duration}: 401 \[Invalid environment token\]
        {date} {info} Ingest failed {duration}: No authentication details
        OUTPUT, $output);
        $ingestBrowser->assertSent([]);
        $ingestBrowser->assertPending([]);
        $loop->assertRun([
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 10, runAt: 10, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
        ]);
        $loop->assertPending([
            new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
        ]);
        $ingestDetailsBrowser->assertSent([
            Request::json('/api/agent-auth'),
        ]);
        $ingestDetailsBrowser->assertPending([]);
    }

    public function test_it_limits_response_body_included_in_logs(): void
    {
        $loop = new LoopFake(runForSeconds: 22);
        $server = new TcpServerFake;
        $ingestDetailsBrowser = new BrowserFake([
            Response::jwt(),
        ]);
        $ingestBrowser = new BrowserFake([
            Response::internalServerError(str_repeat('a', 1005)),
            Response::internalServerError(str_repeat('a', 1006)),
        ]);
        $loop->addTimer(0, $server->pendingConnection([['t' => 'request']]));
        $loop->addTimer(11, $server->pendingConnection([['t' => 'request']]));

        [$output, $e] = $this->runAgent(
            via: 'source',
            ingestDetailsBrowser: $ingestDetailsBrowser,
            ingestBrowser: $ingestBrowser,
            loop: $loop,
            server: $server,
        );

        $this->assertNull($e, $e?->getMessage() ?? '');
        $firstBody = str_repeat('a', 1005);
        $secondBody = str_repeat('a', 1000);
        $this->assertLogMatches(<<<OUTPUT
        {date} {info} Authentication successful {duration}
        {date} {info} Ingest failed {duration}: 500 \[{$firstBody}\]
        {date} {info} Ingest failed {duration}: 500 \[{$secondBody}\[\.\.\.\]\]
        OUTPUT, $output);
        $ingestBrowser->assertSent([
            Request::ingest([['t' => 'request']]),
            Request::ingest([['t' => 'request']]),
        ]);
        $ingestBrowser->assertPending([]);
        $loop->assertRun([
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 10, runAt: 10, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
            new Timer(interval: 11, runAt: 11, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 10, runAt: 21, scheduledAt: 11, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
        ]);
        $loop->assertPending([
            new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
        ]);
        $ingestDetailsBrowser->assertSent([
            Request::json('/api/agent-auth'),
        ]);
        $ingestDetailsBrowser->assertPending([]);
    }

    #[DataProvider('ingestDelayAndLogOutput')]
    public function test_it_waits_on_the_resolution_of_the_ingest_details_before_attempting_to_ingest(int $duration, string $log): void
    {
        $loop = new LoopFake(runForSeconds: 2);
        $server = new TcpServerFake;
        $ingestDetailsBrowser = new BrowserFake([
            Response::jwt(duration: $duration),
        ]);
        $ingestBrowser = new BrowserFake([
            Response::ingested(),
        ]);
        $records = array_fill(0, 375_001, ['t' => 'request']);
        $loop->addTimer(0, $server->pendingConnection($records));

        [$output, $e] = $this->runAgent(
            via: 'source',
            ingestDetailsBrowser: $ingestDetailsBrowser,
            ingestBrowser: $ingestBrowser,
            loop: $loop,
            server: $server,
        );

        $this->assertNull($e, $e?->getMessage() ?? '');
        $this->assertLogMatches($log, $output);
        $ingestBrowser->assertSent($duration === 1
            ? [Request::ingest($records)]
            : []);
        $ingestBrowser->assertProcessing([]);
        $ingestBrowser->assertPending($duration === 1
            ? []
            : [Response::ingested()]);
        $loop->assertRun([
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
            ...($duration === 1
                ? [new Timer(interval: $duration, runAt: $duration, scheduledAt: 0, scheduledBy: 'Tests\Response::toPromise')]
                : []),
        ]);
        $loop->assertPending($duration === 1
            ? [new Timer(interval: 3_600, runAt: 3_601, scheduledAt: 1, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn')]
            : [new Timer(interval: $duration, runAt: $duration, scheduledAt: 0, scheduledBy: 'Tests\Response::toPromise')]);
        $ingestDetailsBrowser->assertSent([
            Request::json('/api/agent-auth'),
        ]);
        $ingestDetailsBrowser->assertProcessing($duration === 1
            ? []
            : [Response::jwt(duration: $duration)]);
        $ingestDetailsBrowser->assertPending([]);
    }

    /**
     * @return iterable<array{0: int, 1: string}>
     */
    public static function ingestDelayAndLogOutput(): iterable
    {
        yield [1, <<<'LOG'
            {date} {info} Authentication successful {duration}
            {date} {info} Ingest successful {duration}
            LOG];
        yield [2, ''];
    }

    public function test_it_handles_runtime_errors_while_waiting_to_authenticate(): void
    {
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

        [$output, $e] = $this->runAgent(
            via: 'source',
            ingestDetailsBrowser: $ingestDetailsBrowser,
            ingestBrowser: $ingestBrowser,
            loop: $loop,
            server: $server,
        );

        $this->assertNull($e, $e?->getMessage() ?? '');
        $this->assertLogMatches(<<<'OUTPUT'
        {date} {info} Authentication failed {duration}: Whoops!
        {date} {info} Ingest failed {duration}: No authentication details
        OUTPUT, $output);
        $ingestBrowser->assertSent([]);
        $ingestBrowser->assertPending([]);
        $loop->assertRun([
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 1, runAt: 1, scheduledAt: 0, scheduledBy: 'Tests\Response::toPromise'),
        ]);
        $loop->assertPending([
            new Timer(interval: 2.5, runAt: 3.5, scheduledAt: 1, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
        ]);
        $ingestDetailsBrowser->assertSent([
            Request::json('/api/agent-auth'),
        ]);
        $ingestDetailsBrowser->assertPending([]);
    }

    public function test_it_handles_error_responses_while_waiting_to_authenticate(): void
    {
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

        [$output, $e] = $this->runAgent(
            via: 'source',
            ingestDetailsBrowser: $ingestDetailsBrowser,
            ingestBrowser: $ingestBrowser,
            loop: $loop,
            server: $server,
        );

        $this->assertNull($e, $e?->getMessage() ?? '');
        $this->assertLogMatches(<<<'OUTPUT'
        {date} {info} Authentication failed {duration}: 500 \[Whoops!\]
        {date} {info} Ingest failed {duration}: No authentication details
        OUTPUT, $output);
        $ingestBrowser->assertSent([]);
        $ingestBrowser->assertPending([]);
        $loop->assertRun([
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 1, runAt: 1, scheduledAt: 0, scheduledBy: 'Tests\Response::toPromise'),
        ]);
        $loop->assertPending([
            new Timer(interval: 2.5, runAt: 3.5, scheduledAt: 1, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
        ]);
        $ingestDetailsBrowser->assertSent([
            Request::json('/api/agent-auth'),
        ]);
        $ingestDetailsBrowser->assertPending([]);
    }

    public function test_it_can_have_two_concurrent_ingest_requests(): void
    {
        $loop = new LoopFake(runForSeconds: 10);
        $server = new TcpServerFake;
        $ingestDetailsBrowser = new BrowserFake([
            Response::jwt(),
        ]);
        $ingestBrowser = new BrowserFake([
            Response::ingested(duration: 3),
            Response::ingested(duration: 4),
        ]);
        $records = array_fill(0, 375_001, ['t' => 'request']);
        $loop->addTimer(0, $server->pendingConnection($records));
        $loop->addTimer(0, $server->pendingConnection($records));

        [$output, $e] = $this->runAgent(
            via: 'source',
            ingestDetailsBrowser: $ingestDetailsBrowser,
            ingestBrowser: $ingestBrowser,
            loop: $loop,
            server: $server,
        );

        $this->assertNull($e, $e?->getMessage() ?? '');
        $this->assertLogMatches(<<<'OUTPUT'
        {date} {info} Authentication successful {duration}
        {date} {info} Ingest successful {duration}
        {date} {info} Ingest successful {duration}
        OUTPUT, $output);
        $ingestBrowser->assertSent([
            Request::ingest($records),
            Request::ingest($records),
        ]);
        $ingestDetailsBrowser->assertProcessing([]);
        $ingestBrowser->assertPending([]);
        $loop->assertRun([
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 3, runAt: 3, scheduledAt: 0, scheduledBy: 'Tests\Response::toPromise'),
            new Timer(interval: 4, runAt: 4, scheduledAt: 0, scheduledBy: 'Tests\Response::toPromise'),
        ]);
        $loop->assertPending([
            new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
        ]);
        $ingestDetailsBrowser->assertSent([
            Request::json('/api/agent-auth'),
        ]);
        $ingestDetailsBrowser->assertProcessing([]);
        $ingestDetailsBrowser->assertPending([]);
    }

    public function test_it_can_have_no_more_than_two_concurrent_ingest_requests(): void
    {
        $loop = new LoopFake(runForSeconds: 10);
        $server = new TcpServerFake;
        $ingestDetailsBrowser = new BrowserFake([
            Response::jwt(),
        ]);
        $ingestBrowser = new BrowserFake([
            Response::ingested(duration: 3),
            Response::ingested(duration: 4),
        ]);
        $records = array_fill(0, 375_001, ['t' => 'request']);
        $loop->addTimer(0, $server->pendingConnection($records));
        $loop->addTimer(0, $server->pendingConnection($records));
        $loop->addTimer(0, $server->pendingConnection($records));
        $loop->addTimer(0, $server->pendingConnection($records));

        [$output, $e] = $this->runAgent(
            via: 'source',
            ingestDetailsBrowser: $ingestDetailsBrowser,
            ingestBrowser: $ingestBrowser,
            loop: $loop,
            server: $server,
        );

        $this->assertNull($e, $e?->getMessage() ?? '');
        $this->assertLogMatches(<<<'OUTPUT'
        {date} {info} Authentication successful {duration}
        {date} {info} Ingest failed {duration}: Exceeded concurrent request limit\. \[2\] requests are processing
        {date} {info} Ingest failed {duration}: Exceeded concurrent request limit\. \[2\] requests are processing
        {date} {info} Ingest successful {duration}
        {date} {info} Ingest successful {duration}
        OUTPUT, $output);
        $ingestBrowser->assertSent([
            Request::ingest($records),
            Request::ingest($records),
        ]);
        $ingestDetailsBrowser->assertProcessing([]);
        $ingestBrowser->assertPending([]);
        $loop->assertRun([
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 3, runAt: 3, scheduledAt: 0, scheduledBy: 'Tests\Response::toPromise'),
            new Timer(interval: 4, runAt: 4, scheduledAt: 0, scheduledBy: 'Tests\Response::toPromise'),
        ]);
        $loop->assertPending([
            new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
        ]);
        $ingestDetailsBrowser->assertSent([
            Request::json('/api/agent-auth'),
        ]);
        $ingestDetailsBrowser->assertProcessing([]);
        $ingestDetailsBrowser->assertPending([]);
    }

    public function test_it_can_have_two_concurrent_requests_ongoing(): void
    {
        $loop = new LoopFake(runForSeconds: 14);
        $server = new TcpServerFake;
        $ingestDetailsBrowser = new BrowserFake([
            Response::jwt(),
        ]);
        $ingestBrowser = new BrowserFake([
            Response::ingested(duration: 2),
            Response::ingested(duration: 2),
            //
            Response::ingested(duration: 2),
            Response::ingested(duration: 2),
            //
            Response::ingested(duration: 2),
            Response::ingested(duration: 2),
            //
            Response::ingested(duration: 1),
            Response::ingested(duration: 1),
            Response::ingested(duration: 1),
            Response::ingested(duration: 1),
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

        [$output, $e] = $this->runAgent(
            via: 'source',
            ingestDetailsBrowser: $ingestDetailsBrowser,
            ingestBrowser: $ingestBrowser,
            loop: $loop,
            server: $server,
            timeout: 10.0,
        );

        $this->assertNull($e, $e?->getMessage() ?? '');
        $this->assertLogMatches(<<<'OUTPUT'
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
        OUTPUT, $output);

        $ingestBrowser->assertSent([
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
        $ingestDetailsBrowser->assertProcessing([]);
        $ingestBrowser->assertPending([]);
        $loop->assertRun([
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 2, runAt: 2, scheduledAt: 0, scheduledBy: 'Tests\Response::toPromise'),
            new Timer(interval: 2, runAt: 2, scheduledAt: 0, scheduledBy: 'Tests\Response::toPromise'),
            new Timer(interval: 3, runAt: 3, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 3, runAt: 3, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 2, runAt: 5, scheduledAt: 3, scheduledBy: 'Tests\Response::toPromise'),
            new Timer(interval: 2, runAt: 5, scheduledAt: 3, scheduledBy: 'Tests\Response::toPromise'),
            new Timer(interval: 6, runAt: 6, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 6, runAt: 6, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 2, runAt: 8, scheduledAt: 6, scheduledBy: 'Tests\Response::toPromise'),
            new Timer(interval: 2, runAt: 8, scheduledAt: 6, scheduledBy: 'Tests\Response::toPromise'),
            new Timer(interval: 9, runAt: 9, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 10, runAt: 10, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 1, runAt: 10, scheduledAt: 9, scheduledBy: 'Tests\Response::toPromise'),
            new Timer(interval: 11, runAt: 11, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 1, runAt: 11, scheduledAt: 10, scheduledBy: 'Tests\Response::toPromise'),
            new Timer(interval: 12, runAt: 12, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 1, runAt: 12, scheduledAt: 11, scheduledBy: 'Tests\Response::toPromise'),
            new Timer(interval: 1, runAt: 13, scheduledAt: 12, scheduledBy: 'Tests\Response::toPromise'),
        ]);
        $loop->assertPending([
            new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
        ]);
        $ingestDetailsBrowser->assertSent([
            Request::json('/api/agent-auth'),
        ]);
        $ingestDetailsBrowser->assertProcessing([]);
        $ingestDetailsBrowser->assertPending([]);
    }

    public function test_it_schedules_an_ingest_when_buffer_is_empty_and_a_payload_under_the_threshold_is_received(): void
    {
        $loop = new LoopFake(runForSeconds: 10);
        $server = new TcpServerFake;
        $ingestDetailsBrowser = new BrowserFake([
            Response::jwt(),
        ]);
        $ingestBrowser = new BrowserFake([]);
        $loop->addTimer(0, $server->pendingConnection([['t' => 'request']]));
        $loop->addTimer(1, $server->pendingConnection([['t' => 'request']]));
        $loop->addTimer(2, $server->pendingConnection([['t' => 'request']]));

        [$output, $e] = $this->runAgent(
            via: 'source',
            ingestDetailsBrowser: $ingestDetailsBrowser,
            ingestBrowser: $ingestBrowser,
            loop: $loop,
            server: $server,
        );

        $this->assertNull($e, $e?->getMessage() ?? '');
        $this->assertLogMatches(<<<'OUTPUT'
        {date} {info} Authentication successful {duration}
        OUTPUT, $output);
        $ingestBrowser->assertSent([]);
        $ingestBrowser->assertProcessing([]);
        $ingestBrowser->assertPending([]);
        $loop->assertRun([
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 1, runAt: 1, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 2, runAt: 2, scheduledAt: 0, scheduledBy: $this->functionName()),
        ]);
        $loop->assertPending([
            new Timer(interval: 10, runAt: 10, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
            new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
        ]);
        $ingestDetailsBrowser->assertSent([
            Request::json('/api/agent-auth'),
        ]);
        $ingestDetailsBrowser->assertPending([]);
    }

    public function test_it_ingests_payloads_under_the_threshold_after_10_seconds(): void
    {
        $loop = new LoopFake(runForSeconds: 11);
        $server = new TcpServerFake;
        $ingestDetailsBrowser = new BrowserFake([
            Response::jwt(),
        ]);
        $ingestBrowser = new BrowserFake([
            Response::ingested(),
        ]);
        $loop->addTimer(0, $server->pendingConnection([['t' => 'request']]));

        [$output, $e] = $this->runAgent(
            via: 'source',
            ingestDetailsBrowser: $ingestDetailsBrowser,
            ingestBrowser: $ingestBrowser,
            loop: $loop,
            server: $server,
        );

        $this->assertNull($e, $e?->getMessage() ?? '');
        $this->assertLogMatches(<<<'OUTPUT'
        {date} {info} Authentication successful {duration}
        {date} {info} Ingest successful {duration}
        OUTPUT, $output);
        $ingestBrowser->assertSent([
            Request::ingest([['t' => 'request']]),
        ]);
        $ingestBrowser->assertProcessing([]);
        $ingestBrowser->assertPending([]);
        $loop->assertRun([
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 10, runAt: 10, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
        ]);
        $loop->assertPending([
            new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
        ]);
        $ingestDetailsBrowser->assertSent([
            Request::json('/api/agent-auth'),
        ]);
        $ingestDetailsBrowser->assertPending([]);
    }

    public function test_it_ingests_payloads_before_10_seconds_if_the_buffer_exceeds_the_threshold(): void
    {
        $loop = new LoopFake(runForSeconds: 11);
        $server = new TcpServerFake;
        $ingestDetailsBrowser = new BrowserFake([
            Response::jwt(),
        ]);
        $ingestBrowser = new BrowserFake([
            Response::ingested(),
        ]);
        $loop->addTimer(0, $server->pendingConnection([['t' => 'request']]));
        $loop->addTimer(1, $server->pendingConnection([['t' => 'request']]));
        $loop->addTimer(2, $server->pendingConnection([['t' => 'request']]));
        $records = array_fill(0, 375_001, ['t' => 'request']);
        $loop->addTimer(3, $server->pendingConnection($records));

        [$output, $e] = $this->runAgent(
            via: 'source',
            ingestDetailsBrowser: $ingestDetailsBrowser,
            ingestBrowser: $ingestBrowser,
            loop: $loop,
            server: $server,
        );

        $this->assertNull($e, $e?->getMessage() ?? '');
        $this->assertLogMatches(<<<'OUTPUT'
        {date} {info} Authentication successful {duration}
        {date} {info} Ingest successful {duration}
        OUTPUT, $output);
        $ingestBrowser->assertSent([
            Request::ingest([
                ['t' => 'request'],
                ['t' => 'request'],
                ['t' => 'request'],
                ...$records,
            ]),
        ]);
        $ingestBrowser->assertProcessing([]);
        $ingestBrowser->assertPending([]);
        $loop->assertRun([
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 1, runAt: 1, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 2, runAt: 2, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 3, runAt: 3, scheduledAt: 0, scheduledBy: $this->functionName()),
        ]);
        $loop->assertCanceled([
            new Timer(interval: 10, canceledAt: 3, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
        ]);
        $loop->assertPending([
            new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
        ]);
        $ingestDetailsBrowser->assertSent([
            Request::json('/api/agent-auth'),
        ]);
        $ingestDetailsBrowser->assertPending([]);
    }

    public function test_it_ingests_immediately_when_buffer_is_empty_and_a_payload_over_the_threshold_is_received(): void
    {
        $loop = new LoopFake(runForSeconds: 1);
        $server = new TcpServerFake;
        $ingestDetailsBrowser = new BrowserFake([
            Response::jwt(),
        ]);
        $ingestBrowser = new BrowserFake([
            Response::ingested(),
        ]);
        $records = array_fill(0, 375_001, ['t' => 'request']);
        $loop->addTimer(0, $server->pendingConnection($records));

        [$output, $e] = $this->runAgent(
            via: 'source',
            ingestDetailsBrowser: $ingestDetailsBrowser,
            ingestBrowser: $ingestBrowser,
            loop: $loop,
            server: $server,
        );

        $this->assertNull($e, $e?->getMessage() ?? '');
        $this->assertLogMatches(<<<'OUTPUT'
        {date} {info} Authentication successful {duration}
        {date} {info} Ingest successful {duration}
        OUTPUT, $output);
        $ingestBrowser->assertSent([
            Request::ingest($records),
        ]);
        $ingestBrowser->assertProcessing([]);
        $ingestBrowser->assertPending([]);
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
    }

    public function test_it_stops_ingesting_data_when_exceeding_quota_during_request(): void
    {
        $loop = new LoopFake(runForSeconds: 60);
        $server = new TcpServerFake;
        $ingestDetailsBrowser = new BrowserFake([
            Response::jwt(),
        ]);
        $ingestBrowser = new BrowserFake([
            Response::ingested(),
            new Response(['stop' => true, 'message' => 'Quota exceeded']),
        ]);
        $loop->addTimer(0, $server->pendingConnection([['t' => 'request']]));
        $loop->addTimer(11, $server->pendingConnection([['t' => 'request']]));
        $loop->addTimer(22, $server->pendingConnection([['t' => 'request']]));

        [$output, $e] = $this->runAgent(
            via: 'source',
            ingestDetailsBrowser: $ingestDetailsBrowser,
            ingestBrowser: $ingestBrowser,
            loop: $loop,
            server: $server,
        );

        $this->assertNull($e, $e?->getMessage() ?? '');
        $this->assertLogMatches(<<<'OUTPUT'
        {date} {info} Authentication successful {duration}
        {date} {info} Ingest successful {duration}
        {date} {info} Ingest attempted {duration}: 200 \[Quota exceeded\]
        OUTPUT, $output);
        $ingestBrowser->assertSent([
            Request::ingest([['t' => 'request']]),
            Request::ingest([['t' => 'request']]),
        ]);
        $ingestBrowser->assertPending([]);
        $loop->assertRun([
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 10, runAt: 10, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
            new Timer(interval: 11, runAt: 11, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 10, runAt: 21, scheduledAt: 11, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
            new Timer(interval: 22, runAt: 22, scheduledAt: 0, scheduledBy: $this->functionName()),
        ]);
        $loop->assertCanceled([
            new Timer(interval: 3_600, canceledAt: 21, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
        ]);
        $loop->assertPending([
            new Timer(interval: 900, runAt: 921, scheduledAt: 21, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
        ]);
        $ingestDetailsBrowser->assertSent([
            Request::json('/api/agent-auth'),
        ]);
        $ingestDetailsBrowser->assertPending([]);
    }

    public function test_it_stops_ingesting_data_when_already_exceeded_quota(): void
    {
        $loop = new LoopFake(runForSeconds: 23);
        $server = new TcpServerFake;
        $ingestDetailsBrowser = new BrowserFake([
            Response::jwt(),
        ]);
        $ingestBrowser = new BrowserFake([
            Response::ingested(),
            new Response(['stop' => true, 'message' => 'Quota exceeded'], status: 403),
        ]);
        $loop->addTimer(0, $server->pendingConnection([['t' => 'request']]));
        $loop->addTimer(11, $server->pendingConnection([['t' => 'request']]));
        $loop->addTimer(22, $server->pendingConnection([['t' => 'request']]));

        [$output, $e] = $this->runAgent(
            via: 'source',
            ingestDetailsBrowser: $ingestDetailsBrowser,
            ingestBrowser: $ingestBrowser,
            loop: $loop,
            server: $server,
        );

        $this->assertNull($e, $e?->getMessage() ?? '');

        $this->assertLogMatches(<<<'OUTPUT'
            {date} {info} Authentication successful {duration}
            {date} {info} Ingest successful {duration}
            {date} {info} Ingest failed {duration}: 403 \[Quota exceeded\]
            OUTPUT, $output);

        $ingestBrowser
            ->assertPending([])
            ->assertSent([
                Request::ingest([['t' => 'request']]),
                Request::ingest([['t' => 'request']]),
            ])
            ->assertPending([]);

        $loop
            ->assertPending([
                new Timer(interval: 900, runAt: 921, scheduledAt: 21, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
            ])
            ->assertRun([
                new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
                new Timer(interval: 10, runAt: 10, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
                new Timer(interval: 11, runAt: 11, scheduledAt: 0, scheduledBy: $this->functionName()),
                new Timer(interval: 10, runAt: 21, scheduledAt: 11, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
                new Timer(interval: 22, runAt: 22, scheduledAt: 0, scheduledBy: $this->functionName()),
            ])
            ->assertCanceled([
                new Timer(interval: 3_600, canceledAt: 21, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
            ]);

        $ingestDetailsBrowser
            ->assertPending([])
            ->assertSent([
                Request::json('/api/agent-auth'),
            ])
            ->assertProcessing([]);
    }

    public function test_it_starts_ingesting_data_after_a_subsequent_successful_authentication(): void
    {
        $loop = new LoopFake(runForSeconds: 933);
        $server = new TcpServerFake;
        $ingestDetailsBrowser = new BrowserFake([
            Response::jwt(),
            Response::jwt(),
        ]);
        $ingestBrowser = new BrowserFake([
            Response::ingested(),
            Response::ingested(['stop' => true, 'message' => 'Quota exceeded']),
            Response::ingested(['stop' => true, 'message' => 'Quota exceeded']),
        ]);
        $loop->addTimer(0, $server->pendingConnection([['t' => 'request 1']]));
        $loop->addTimer(11, $server->pendingConnection([['t' => 'request 2']]));
        $loop->addTimer(22, $server->pendingConnection([['t' => 'request 3']]));
        $loop->addTimer(922, $server->pendingConnection([['t' => 'request 4']]));

        [$output, $e] = $this->runAgent(
            via: 'source',
            ingestDetailsBrowser: $ingestDetailsBrowser,
            ingestBrowser: $ingestBrowser,
            loop: $loop,
            server: $server,
        );

        $this->assertNull($e, $e?->getMessage() ?? '');
        $this->assertLogMatches(<<<'OUTPUT'
        {date} {info} Authentication successful {duration}
        {date} {info} Ingest successful {duration}
        {date} {info} Ingest attempted {duration}: 200 \[Quota exceeded\]
        {date} {info} Authentication successful {duration}
        {date} {info} Ingest attempted {duration}: 200 \[Quota exceeded\]
        OUTPUT, $output);
        $ingestBrowser->assertSent([
            Request::ingest([['t' => 'request 1']]),
            Request::ingest([['t' => 'request 2']]),
            Request::ingest([['t' => 'request 4']]),
        ]);
        $ingestBrowser->assertPending([]);
        $loop->assertRun([
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 10, runAt: 10, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
            new Timer(interval: 11, runAt: 11, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 10, runAt: 21, scheduledAt: 11, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
            new Timer(interval: 22, runAt: 22, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 900, runAt: 921, scheduledAt: 21, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
            new Timer(interval: 922, runAt: 922, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 10, runAt: 932, scheduledAt: 922, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
        ]);
        $loop->assertCanceled([
            new Timer(interval: 3_600, canceledAt: 21, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
            new Timer(interval: 3_600, canceledAt: 932, scheduledAt: 921, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
        ]);
        $loop->assertPending([
            new Timer(interval: 900, runAt: 900 + 932, scheduledAt: 932, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
        ]);
        $ingestDetailsBrowser->assertSent([
            Request::json('/api/agent-auth'),
            Request::json('/api/agent-auth'),
        ]);
        $ingestDetailsBrowser->assertPending([]);
    }

    public function test_it_handles_incomplete_payloads(): void
    {
        $loop = new LoopFake(runForSeconds: 8);
        $server = new TcpServerFake;
        $signature = $this->agentSignature();
        $signaturePart = substr($signature, 0, 2);
        $ingestDetailsBrowser = new BrowserFake([
            Response::jwt(),
        ]);
        $ingestBrowser = new BrowserFake([]);
        $loop->addTimer(0, $server->pendingConnection('12'));
        $loop->addTimer(1, $server->pendingConnection('12:'));
        $loop->addTimer(2, $server->pendingConnection("12:{$signaturePart}"));
        $loop->addTimer(3, $server->pendingConnection("12:{$signature}"));
        $loop->addTimer(4, $server->pendingConnection("12:{$signature}:["));
        $loop->addTimer(5, $server->pendingConnection("12:{$signature}:[{"));
        $loop->addTimer(6, $server->pendingConnection("12:{$signature}:[{}"));
        $loop->addTimer(7, $server->pendingConnection("12:{$signature}:[{}]"));

        [$output, $e] = $this->runAgent(
            via: 'source',
            ingestDetailsBrowser: $ingestDetailsBrowser,
            ingestBrowser: $ingestBrowser,
            loop: $loop,
            server: $server,
        );

        $this->assertNull($e, $e?->getMessage() ?? '');
        $this->assertLogMatches(<<<OUTPUT
            {date} {info} Authentication successful {duration}
            {date} {error} Connection error: Incomplete payload received\. Length: \[\] Value: \[12\]
            {date} {error} Connection error: Incomplete payload received\. Length: \[\] Value: \[12:\]
            {date} {error} Connection error: Incomplete payload received\. Length: \[\] Value: \[12:{$signaturePart}\]
            {date} {error} Connection error: Incomplete payload received\. Length: \[\] Value: \[12:{$signature}\]
            {date} {error} Connection error: Incomplete payload received\. Length: \[12\] Value: \[\[\]
            {date} {error} Connection error: Incomplete payload received\. Length: \[12\] Value: \[\[\{\]
            {date} {error} Connection error: Incomplete payload received\. Length: \[12\] Value: \[\[\{\}\]
            OUTPUT, $output);
        $loop->assertRun([
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 1, runAt: 1, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 2, runAt: 2, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 3, runAt: 3, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 4, runAt: 4, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 5, runAt: 5, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 6, runAt: 6, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 7, runAt: 7, scheduledAt: 0, scheduledBy: $this->functionName()),
        ]);
        $loop->assertPending([
            new Timer(interval: 10, runAt: 17, scheduledAt: 7, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
            new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
        ]);
        $ingestDetailsBrowser->assertSent([
            Request::json('/api/agent-auth'),
        ]);
    }

    public function test_it_sends_pending_records_on_invalid_signature(): void
    {
        $loop = new LoopFake(runForSeconds: 2);
        $server = new TcpServerFake;
        $ingestDetailsBrowser = new BrowserFake([Response::jwt()]);
        $ingestBrowser = new BrowserFake([
            Response::ingested(),
        ]);
        $loop->addTimer(0, $server->pendingConnection([['t' => 'request']]));
        $loop->addTimer(1, $server->pendingConnection('12:INVALID:[{}]'));

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
            Connection::ok(),
        ]);
        $server->assertClosed();
        $this->assertLogMatches(<<<'OUTPUT'
        {date} {info} Authentication successful {duration}
        {date} {info} Incoming signature has changed
        {date} {info} Ingest successful {duration}
        {date} {info} Shutting down
        OUTPUT, $output);
        $loop->assertRun([
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 1, runAt: 1, scheduledAt: 0, scheduledBy: $this->functionName()),
        ]);
        $loop->assertCanceled([
            new Timer(interval: 10, canceledAt: 1, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
        ]);
        $loop->assertPending([
            new Timer(interval: 3_600, runAt: null, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
        ]);
        $this->assertTrue($loop->stopped);
        $ingestDetailsBrowser->assertSent([
            Request::json('/api/agent-auth'),
        ]);
        $ingestDetailsBrowser->assertPending([]);
        $ingestBrowser->assertSent([
            Request::ingest([['t' => 'request']]),
        ]);
        $ingestBrowser->assertPending([]);
    }

    public function test_it_does_not_make_ingest_request_on_shutdown_if_buffer_is_currently_empty(): void
    {
        $loop = new LoopFake(runForSeconds: 2);
        $server = new TcpServerFake;
        $ingestDetailsBrowser = new BrowserFake([Response::jwt()]);
        $ingestBrowser = new BrowserFake([]);
        $loop->addTimer(1, $server->pendingConnection('12:INVALID:[{}]'));

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
        {date} {info} Incoming signature has changed
        {date} {info} Shutting down
        OUTPUT, $output);
        $loop->assertRun([
            new Timer(interval: 1, runAt: 1, scheduledAt: 0, scheduledBy: $this->functionName()),
        ]);
        $loop->assertCanceled([]);
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

    public function test_it_waits_on_active_requests_on_shutdown(): void
    {
        $loop = new LoopFake(runForSeconds: 16);
        $server = new TcpServerFake;
        $ingestDetailsBrowser = new BrowserFake([
            Response::jwt(),
        ]);
        $ingestBrowser = new BrowserFake([
            Response::ingested(duration: 5),
        ]);
        $loop->addTimer(0, $server->pendingConnection([['t' => 'request']]));
        $loop->addTimer(11, $server->pendingConnection('12:INVALID:[{}]'));

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
            Connection::ok(),
        ]);
        $server->assertClosed();
        $this->assertLogMatches(<<<'OUTPUT'
        {date} {info} Authentication successful {duration}
        {date} {info} Incoming signature has changed
        {date} {info} Ingest successful {duration}
        {date} {info} Shutting down
        OUTPUT, $output);
        $loop->assertRun([
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 10, runAt: 10, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
            new Timer(interval: 11, runAt: 11, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 5, runAt: 15, scheduledAt: 10, scheduledBy: 'Tests\Response::toPromise'),
        ]);
        $loop->assertCanceled([]);
        $loop->assertPending([
            new Timer(interval: 3_600, runAt: null, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
        ]);
        $this->assertTrue($loop->stopped);
        $ingestDetailsBrowser->assertSent([
            Request::json('/api/agent-auth'),
        ]);
        $ingestDetailsBrowser->assertPending([]);
        $ingestBrowser->assertSent([
            Request::ingest([['t' => 'request']]),
        ]);
        $ingestBrowser->assertPending([]);
    }
}
