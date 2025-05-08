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
use function expect;
use function gethostname;
use function run;
use function signature;
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
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
        ]);
        expect($loop)->toHavePending([
            new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
        ]);
        expect($ingestDetailsBrowser)->toHaveSent([
            Request::json('/api/agent-auth'),
        ]);
        expect($ingestDetailsBrowser)->toHavePending([]);
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
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 10, runAt: 10, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
        ]);
        expect($loop)->toHavePending([
            new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
        ]);
        expect($ingestDetailsBrowser)->toHaveSent([
            Request::json('/api/agent-auth'),
        ]);
        expect($ingestDetailsBrowser)->toHavePending([]);
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
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 10, runAt: 10, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
        ]);
        expect($loop)->toHavePending([
            new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
        ]);
        expect($ingestDetailsBrowser)->toHaveSent([
            Request::json('/api/agent-auth'),
        ]);
        expect($ingestDetailsBrowser)->toHavePending([]);
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
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 10, runAt: 10, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
        ]);
        expect($loop)->toHavePending([
            new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
        ]);
        expect($ingestDetailsBrowser)->toHaveSent([
            Request::json('/api/agent-auth'),
        ]);
        expect($ingestDetailsBrowser)->toHavePending([]);
    }

    public function test_it_limits_response_body_included_in_logs(): void
    {
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
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 10, runAt: 10, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
            new Timer(interval: 11, runAt: 11, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 10, runAt: 21, scheduledAt: 11, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
        ]);
        expect($loop)->toHavePending([
            new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
        ]);
        expect($ingestDetailsBrowser)->toHaveSent([
            Request::json('/api/agent-auth'),
        ]);
        expect($ingestDetailsBrowser)->toHavePending([]);
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
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
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
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 1, runAt: 1, scheduledAt: 0, scheduledBy: 'Tests\Response::toPromise'),
        ]);
        expect($loop)->toHavePending([
            new Timer(interval: 2.5, runAt: 3.5, scheduledAt: 1, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
        ]);
        expect($ingestDetailsBrowser)->toHaveSent([
            Request::json('/api/agent-auth'),
        ]);
        expect($ingestDetailsBrowser)->toHavePending([]);
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
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 1, runAt: 1, scheduledAt: 0, scheduledBy: 'Tests\Response::toPromise'),
        ]);
        expect($loop)->toHavePending([
            new Timer(interval: 2.5, runAt: 3.5, scheduledAt: 1, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
        ]);
        expect($ingestDetailsBrowser)->toHaveSent([
            Request::json('/api/agent-auth'),
        ]);
        expect($ingestDetailsBrowser)->toHavePending([]);
    }

    public function test_it_can_have_two_concurrent_ingest_requests(): void
    {
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
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
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
    }

    public function test_it_can_have_no_more_than_two_concurrent_ingest_requests(): void
    {
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
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
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
    }

    public function test_it_can_have_two_concurrent_requests_ongoing(): void
    {
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
        expect($loop)->toHavePending([
            new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
        ]);
        expect($ingestDetailsBrowser)->toHaveSent([
            Request::json('/api/agent-auth'),
        ]);
        expect($ingestDetailsBrowser)->toBeProcessing([]);
        expect($ingestDetailsBrowser)->toHavePending([]);
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
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 1, runAt: 1, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 2, runAt: 2, scheduledAt: 0, scheduledBy: $this->functionName()),
        ]);
        expect($loop)->toHavePending([
            new Timer(interval: 10, runAt: 10, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
            new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
        ]);
        expect($ingestDetailsBrowser)->toHaveSent([
            Request::json('/api/agent-auth'),
        ]);
        expect($ingestDetailsBrowser)->toHavePending([]);
    }

    public function test_it_ingests_payloads_under_the_threshold_after_10_seconds(): void
    {
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
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 10, runAt: 10, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
        ]);
        expect($loop)->toHavePending([
            new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
        ]);
        expect($ingestDetailsBrowser)->toHaveSent([
            Request::json('/api/agent-auth'),
        ]);
        expect($ingestDetailsBrowser)->toHavePending([]);
    }

    public function test_it_ingests_payloads_before_10_seconds_if_the_buffer_exceeds_the_threshold(): void
    {
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
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 1, runAt: 1, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 2, runAt: 2, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 3, runAt: 3, scheduledAt: 0, scheduledBy: $this->functionName()),
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
    }

    public function test_it_ingests_immediately_when_buffer_is_empty_and_a_payload_over_the_threshold_is_received(): void
    {
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
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
        ]);
        expect($loop)->toHavePending([
            new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
        ]);
        expect($ingestDetailsBrowser)->toHaveSent([
            Request::json('/api/agent-auth'),
        ]);
        expect($ingestDetailsBrowser)->toHavePending([]);
    }

    public function test_it_stops_ingesting_data_when_over_quota(): void
    {
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
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 10, runAt: 10, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
            new Timer(interval: 11, runAt: 11, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 10, runAt: 21, scheduledAt: 11, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
            new Timer(interval: 22, runAt: 22, scheduledAt: 0, scheduledBy: $this->functionName()),
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
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 10, runAt: 10, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
            new Timer(interval: 11, runAt: 11, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 10, runAt: 21, scheduledAt: 11, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
            new Timer(interval: 22, runAt: 22, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 900, runAt: 921, scheduledAt: 21, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
            new Timer(interval: 922, runAt: 922, scheduledAt: 0, scheduledBy: $this->functionName()),
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
    }

    public function test_it_handles_incomplete_payloads(): void
    {
        $loop = new LoopFake(runForSeconds: 8);
        $server = new TcpServerFake;
        $signature = signature();
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

        [$output, $e] = run(
            via: 'source',
            ingestDetailsBrowser: $ingestDetailsBrowser,
            ingestBrowser: $ingestBrowser,
            loop: $loop,
            server: $server,
        );

        expect($e)->toBeNull($e?->getMessage() ?? '');
        expect($output)->toMatchLog(<<<OUTPUT
            {date} {info} Authentication successful {duration}
            {date} {error} Connection error: Incomplete payload received\. Length: \[\] Value: \[12\]
            {date} {error} Connection error: Incomplete payload received\. Length: \[\] Value: \[12:\]
            {date} {error} Connection error: Incomplete payload received\. Length: \[\] Value: \[12:{$signaturePart}\]
            {date} {error} Connection error: Incomplete payload received\. Length: \[\] Value: \[12:{$signature}\]
            {date} {error} Connection error: Incomplete payload received\. Length: \[12\] Value: \[\[\]
            {date} {error} Connection error: Incomplete payload received\. Length: \[12\] Value: \[\[\{\]
            {date} {error} Connection error: Incomplete payload received\. Length: \[12\] Value: \[\[\{\}\]
            OUTPUT);
        expect($loop)->toHaveRun([
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 1, runAt: 1, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 2, runAt: 2, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 3, runAt: 3, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 4, runAt: 4, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 5, runAt: 5, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 6, runAt: 6, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 7, runAt: 7, scheduledAt: 0, scheduledBy: $this->functionName()),
        ]);
        expect($loop)->toHavePending([
            new Timer(interval: 10, runAt: 17, scheduledAt: 7, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
            new Timer(interval: 3_600, runAt: 3_600, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
        ]);
        expect($ingestDetailsBrowser)->toHaveSent([
            Request::json('/api/agent-auth'),
        ]);
    }

    public function test_it_sends_pending_records_on_invalid_signature(): void
    {
        $loop = new LoopFake(runForSeconds: 2);
        $server = new TcpServerFake;
        $ingestDetailsBrowser = new BrowserFake([Response::jwt()]);
        $ingestBrowser = new BrowserFake([
            Response::ingest(),
        ]);
        $loop->addTimer(0, $server->pendingConnection([['t' => 'request']]));
        $loop->addTimer(1, $server->pendingConnection('12:INVALID:[{}]'));

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
            Connection::closed('2:OK'),
        ]);
        expect($server->closed)->toBeTrue();
        expect($output)->toMatchLog(<<<'OUTPUT'
        {date} {info} Authentication successful {duration}
        {date} {info} Incoming signature has changed
        {date} {info} Ingest successful {duration}
        {date} {info} Shutting down
        OUTPUT);
        expect($loop)->toHaveRun([
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 1, runAt: 1, scheduledAt: 0, scheduledBy: $this->functionName()),
        ]);
        expect($loop)->toHaveCanceled([
            new Timer(interval: 10, canceledAt: 1, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
        ]);
        expect($loop)->toHavePending([
            new Timer(interval: 3_600, runAt: null, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
        ]);
        expect($loop->stopped)->toBeTrue();
        expect($ingestDetailsBrowser)->toHaveSent([
            Request::json('/api/agent-auth'),
        ]);
        expect($ingestDetailsBrowser)->toHavePending([]);
        expect($ingestBrowser)->toHaveSent([
            Request::ingest([['t' => 'request']]),
        ]);
        expect($ingestBrowser)->toHavePending([]);
    }

    public function test_it_does_not_make_ingest_request_on_shutdown_if_buffer_is_currently_empty(): void
    {
        $loop = new LoopFake(runForSeconds: 2);
        $server = new TcpServerFake;
        $ingestDetailsBrowser = new BrowserFake([Response::jwt()]);
        $ingestBrowser = new BrowserFake([]);
        $loop->addTimer(1, $server->pendingConnection('12:INVALID:[{}]'));

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
        expect($server->closed)->toBeTrue();
        expect($output)->toMatchLog(<<<'OUTPUT'
        {date} {info} Authentication successful {duration}
        {date} {info} Incoming signature has changed
        {date} {info} Shutting down
        OUTPUT);
        expect($loop)->toHaveRun([
            new Timer(interval: 1, runAt: 1, scheduledAt: 0, scheduledBy: $this->functionName()),
        ]);
        expect($loop)->toHaveCanceled([]);
        expect($loop)->toHavePending([
            new Timer(interval: 3_600, runAt: null, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
        ]);
        expect($loop->stopped)->toBeTrue();
        expect($ingestDetailsBrowser)->toHaveSent([
            Request::json('/api/agent-auth'),
        ]);
        expect($ingestDetailsBrowser)->toHavePending([]);
        expect($ingestBrowser)->toHaveSentNothing();
        expect($ingestBrowser)->toHavePending([]);
    }

    public function test_it_waits_on_active_requests_on_shutdown(): void
    {
        $loop = new LoopFake(runForSeconds: 16);
        $server = new TcpServerFake;
        $ingestDetailsBrowser = new BrowserFake([
            Response::jwt(),
        ]);
        $ingestBrowser = new BrowserFake([
            Response::ingest(duration: 5),
        ]);
        $loop->addTimer(0, $server->pendingConnection([['t' => 'request']]));
        $loop->addTimer(11, $server->pendingConnection('12:INVALID:[{}]'));

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
            Connection::closed('2:OK'),
        ]);
        expect($server->closed)->toBeTrue();
        expect($output)->toMatchLog(<<<'OUTPUT'
        {date} {info} Authentication successful {duration}
        {date} {info} Incoming signature has changed
        {date} {info} Ingest successful {duration}
        {date} {info} Shutting down
        OUTPUT);
        expect($loop)->toHaveRun([
            new Timer(interval: 0, runAt: 0, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 10, runAt: 10, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\Ingest::write'),
            new Timer(interval: 11, runAt: 11, scheduledAt: 0, scheduledBy: $this->functionName()),
            new Timer(interval: 5, runAt: 15, scheduledAt: 10, scheduledBy: 'Tests\Response::toPromise'),
        ]);
        expect($loop)->toHaveCanceled([]);
        expect($loop)->toHavePending([
            new Timer(interval: 3_600, runAt: null, scheduledAt: 0, scheduledBy: 'Laravel\NightwatchAgent\IngestDetailsRepository::scheduleRefreshIn'),
        ]);
        expect($loop->stopped)->toBeTrue();
        expect($ingestDetailsBrowser)->toHaveSent([
            Request::json('/api/agent-auth'),
        ]);
        expect($ingestDetailsBrowser)->toHavePending([]);
        expect($ingestBrowser)->toHaveSent([
            Request::ingest([['t' => 'request']]),
        ]);
        expect($ingestBrowser)->toHavePending([]);
    }
}
