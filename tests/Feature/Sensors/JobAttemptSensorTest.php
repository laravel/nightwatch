<?php

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Testing\WithConsoleEvents;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

use function Pest\Laravel\travelTo;

uses(WithConsoleEvents::class);

beforeAll(function () {
    forceCommandExecutionState();
});

beforeEach(function () {
    setDeploy('v1.2.3');
    setServerName('web-01');
    setPeakMemory(1234);
    setExecutionStart(CarbonImmutable::parse('2000-01-01 01:02:03.456789'));

    setTraceId('0d3ca349-e222-4982-ac23-2343692de258');
    Config::set('queue.default', 'database');
});

it('ingests processed job attempts', function () {
    $ingest = fakeIngest();
    Str::createUuidsUsingSequence([
        $jobId = 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        $attemptId = '02cb9091-8973-427f-8d3f-042f2ec4e862',
    ]);
    ProcessedJob::dispatch();

    Artisan::call('queue:work', ['--max-jobs' => 1, '--stop-when-empty' => true, '--sleep' => 0]);

    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('job-attempt:*', [
        [
            'v' => 1,
            't' => 'job-attempt',
            'timestamp' => 946688523.456789,
            'deploy' => 'v1.2.3',
            'server' => 'web-01',
            '_group' => hash('xxh128', 'ProcessedJob'),
            'trace_id' => '0d3ca349-e222-4982-ac23-2343692de258',
            'user' => '',
            'job_id' => $jobId,
            'attempt_id' => $attemptId,
            'attempt' => 1,
            'name' => 'ProcessedJob',
            'connection' => 'database',
            'queue' => 'default',
            'status' => 'processed',
            'duration' => 2500,
            'exceptions' => 0,
            'logs' => 0,
            'queries' => 4, // Reserve and delete the job
            'lazy_loads' => 0,
            'jobs_queued' => 0,
            'mail' => 0,
            'notifications' => 0,
            'outgoing_requests' => 0,
            'files_read' => 0,
            'files_written' => 0,
            'cache_events' => 0,
            'hydrated_models' => 0,
            'peak_memory_usage' => 1234,
        ],
    ]);
});

it('ingests job released job attempts', function () {
    $ingest = fakeIngest();
    Str::createUuidsUsingSequence([
        $jobId = 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        $attemptId = '02cb9091-8973-427f-8d3f-042f2ec4e862',
    ]);
    ReleasedJob::dispatch();

    Artisan::call('queue:work', ['--max-jobs' => 1, '--stop-when-empty' => true, '--sleep' => 0]);

    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('job-attempt:*', [
        [
            'v' => 1,
            't' => 'job-attempt',
            'timestamp' => 946688523.456789,
            'deploy' => 'v1.2.3',
            'server' => 'web-01',
            '_group' => hash('xxh128', 'ReleasedJob'),
            'trace_id' => '0d3ca349-e222-4982-ac23-2343692de258',
            'user' => '',
            'job_id' => $jobId,
            'attempt_id' => $attemptId,
            'attempt' => 1,
            'name' => 'ReleasedJob',
            'connection' => 'database',
            'queue' => 'default',
            'status' => 'released',
            'duration' => 2500,
            'exceptions' => 0,
            'logs' => 0,
            'queries' => 5, // Reserve, delete, and insert into the jobs table
            'lazy_loads' => 0,
            'jobs_queued' => 0,
            'mail' => 0,
            'notifications' => 0,
            'outgoing_requests' => 0,
            'files_read' => 0,
            'files_written' => 0,
            'cache_events' => 0,
            'hydrated_models' => 0,
            'peak_memory_usage' => 1234,
        ],
    ]);
});

it('ingests job failed job attempts', function () {
    $ingest = fakeIngest();
    Str::createUuidsUsingSequence([
        $jobId = 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        $attemptId = '02cb9091-8973-427f-8d3f-042f2ec4e862',
    ]);
    FailedJob::dispatch();

    Artisan::call('queue:work', ['--max-jobs' => 1, '--stop-when-empty' => true, '--sleep' => 0]);

    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('job-attempt:*', [
        [
            'v' => 1,
            't' => 'job-attempt',
            'timestamp' => 946688523.456789,
            'deploy' => 'v1.2.3',
            'server' => 'web-01',
            '_group' => hash('xxh128', 'FailedJob'),
            'trace_id' => '0d3ca349-e222-4982-ac23-2343692de258',
            'user' => '',
            'job_id' => $jobId,
            'attempt_id' => $attemptId,
            'attempt' => 1,
            'name' => 'FailedJob',
            'connection' => 'database',
            'queue' => 'default',
            'status' => 'failed',
            'duration' => 2500,
            'exceptions' => 1,
            'logs' => 0,
            'queries' => 5, // Reserve and delete the job
            'lazy_loads' => 0,
            'jobs_queued' => 0,
            'mail' => 0,
            'notifications' => 0,
            'outgoing_requests' => 0,
            'files_read' => 0,
            'files_written' => 0,
            'cache_events' => 0,
            'hydrated_models' => 0,
            'peak_memory_usage' => 1234,
        ],
    ]);
    $ingest->assertLatestWrite('exception:0.execution_source', 'job');
    $ingest->assertLatestWrite('exception:0.execution_id', $attemptId);
});

it('does not ingest jobs dispatched on the sync queue', function () {
    $ingest = fakeIngest();
    ProcessedJob::dispatchSync();

    $ingest->assertWrittenTimes(0);
});

it('captures closure job', function () {
    $ingest = fakeIngest();
    Str::createUuidsUsingSequence([
        $jobId = 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        $attemptId = '02cb9091-8973-427f-8d3f-042f2ec4e862',
    ]);
    $line = __LINE__ + 1;
    dispatch(function () {
        travelTo(now()->addMicroseconds(2500));
    });

    Artisan::call('queue:work', ['--max-jobs' => 1, '--stop-when-empty' => true, '--sleep' => 0]);

    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('job-attempt:*', [
        [
            'v' => 1,
            't' => 'job-attempt',
            'timestamp' => 946688523.456789,
            'deploy' => 'v1.2.3',
            'server' => 'web-01',
            '_group' => hash('xxh128', "Closure (JobAttemptSensorTest.php:{$line})"),
            'trace_id' => '0d3ca349-e222-4982-ac23-2343692de258',
            'user' => '',
            'job_id' => $jobId,
            'attempt_id' => $attemptId,
            'attempt' => 1,
            'name' => "Closure (JobAttemptSensorTest.php:{$line})",
            'connection' => 'database',
            'queue' => 'default',
            'status' => 'processed',
            'duration' => 2500,
            'exceptions' => 0,
            'logs' => 0,
            'queries' => 4,
            'lazy_loads' => 0,
            'jobs_queued' => 0,
            'mail' => 0,
            'notifications' => 0,
            'outgoing_requests' => 0,
            'files_read' => 0,
            'files_written' => 0,
            'cache_events' => 0,
            'hydrated_models' => 0,
            'peak_memory_usage' => 1234,
        ],
    ]);
});

it('captures queued event listener', function () {
    $ingest = fakeIngest();
    Str::createUuidsUsingSequence([
        $jobId = 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        $attemptId = '02cb9091-8973-427f-8d3f-042f2ec4e862',
    ]);
    Event::listen(MyEvent::class, MyEventListener::class);
    Event::dispatch(new MyEvent);

    Artisan::call('queue:work', ['--max-jobs' => 1, '--stop-when-empty' => true, '--sleep' => 0]);

    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('job-attempt:*', [
        [
            'v' => 1,
            't' => 'job-attempt',
            'timestamp' => 946688523.456789,
            'deploy' => 'v1.2.3',
            'server' => 'web-01',
            '_group' => hash('xxh128', 'MyEventListener'),
            'trace_id' => '0d3ca349-e222-4982-ac23-2343692de258',
            'user' => '',
            'job_id' => $jobId,
            'attempt_id' => $attemptId,
            'attempt' => 1,
            'name' => 'MyEventListener',
            'connection' => 'database',
            'queue' => 'default',
            'status' => 'processed',
            'duration' => 2500,
            'exceptions' => 0,
            'logs' => 0,
            'queries' => 4,
            'lazy_loads' => 0,
            'jobs_queued' => 0,
            'mail' => 0,
            'notifications' => 0,
            'outgoing_requests' => 0,
            'files_read' => 0,
            'files_written' => 0,
            'cache_events' => 0,
            'hydrated_models' => 0,
            'peak_memory_usage' => 1234,
        ],
    ]);
});

it('captures queued mail', function () {
    $ingest = fakeIngest();
    Str::createUuidsUsingSequence([
        $jobId = 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        $attemptId = '02cb9091-8973-427f-8d3f-042f2ec4e862',
    ]);
    Config::set('mail.default', 'log');

    Mail::to('tim@laravel.com')->queue(new MyQueuedMail);

    Artisan::call('queue:work', ['--max-jobs' => 1, '--stop-when-empty' => true, '--sleep' => 0]);

    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('job-attempt:*', [
        [
            'v' => 1,
            't' => 'job-attempt',
            'timestamp' => 946688523.456789,
            'deploy' => 'v1.2.3',
            'server' => 'web-01',
            '_group' => hash('xxh128', 'MyQueuedMail'),
            'trace_id' => '0d3ca349-e222-4982-ac23-2343692de258',
            'user' => '',
            'job_id' => $jobId,
            'attempt_id' => $attemptId,
            'attempt' => 1,
            'name' => 'MyQueuedMail',
            'connection' => 'database',
            'queue' => 'default',
            'status' => 'processed',
            'duration' => 2500,
            'exceptions' => 0,
            'logs' => 0,
            'queries' => 4,
            'lazy_loads' => 0,
            'jobs_queued' => 0,
            'mail' => 1,
            'notifications' => 0,
            'outgoing_requests' => 0,
            'files_read' => 0,
            'files_written' => 0,
            'cache_events' => 0,
            'hydrated_models' => 0,
            'peak_memory_usage' => 1234,
        ],
    ]);
    $ingest->assertLatestWrite('mail:*', [
        [
            'v' => 1,
            't' => 'mail',
            'timestamp' => 946688523.459289,
            'deploy' => 'v1.2.3',
            'server' => 'web-01',
            '_group' => hash('xxh128', 'MyQueuedMail'),
            'trace_id' => '0d3ca349-e222-4982-ac23-2343692de258',
            'execution_source' => 'job',
            'execution_id' => $attemptId,
            'execution_stage' => 'action',
            'user' => '',
            'mailer' => 'log',
            'class' => 'MyQueuedMail',
            'subject' => 'My Queued Mail',
            'to' => 1,
            'cc' => 0,
            'bcc' => 0,
            'attachments' => 0,
            'duration' => 0,
            'failed' => false,
        ],
    ]);
});

it('captures multiple job attempts', function () {
    $ingest = fakeIngest();
    FailedJob::dispatch();

    Artisan::call('queue:work', ['--max-jobs' => 2, '--tries' => 2, '--stop-when-empty' => true, '--sleep' => 0]);

    $ingest->assertWrittenTimes(2);
    $ingest->assertLatestWrite('job-attempt:0.attempt', 2);
});

final class ProcessedJob implements ShouldQueue
{
    use Queueable;

    public function handle()
    {
        travelTo(now()->addMicroseconds(2500));
    }
}

final class ReleasedJob implements ShouldQueue
{
    use Queueable;

    public function handle()
    {
        travelTo(now()->addMicroseconds(2500));

        $this->release();
    }
}

final class FailedJob implements ShouldQueue
{
    use Queueable;

    public function handle()
    {
        travelTo(now()->addMicroseconds(2500));

        throw new RuntimeException('Job failed');
    }
}

final class MyEventListener implements ShouldQueue
{
    public function handle()
    {
        travelTo(now()->addMicroseconds(2500));
    }
}
