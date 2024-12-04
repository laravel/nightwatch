<?php

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Laravel\Nightwatch\Types\Str;
use Illuminate\Foundation\Testing\WithConsoleEvents;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

use function Orchestra\Testbench\Pest\defineEnvironment;
use function Pest\Laravel\travelTo;

uses(WithConsoleEvents::class);

defineEnvironment(function () {
    forceCommandExecutionState();
    Str::createUuidsUsingSequence([
        $this->traceId = '0d3ca349-e222-4982-ac23-2343692de258',
        $this->jobId = 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        $this->executionId = '3f45e448-0901-4782-aaec-5ec37305f442',
        $this->attemptId = '02cb9091-8973-427f-8d3f-042f2ec4e862',
    ]);
    $this->ingest = fakeIngest();
});

beforeEach(function () {
    setDeploy('v1.2.3');
    setServerName('web-01');
    setPeakMemory(1234);
    setExecutionStart(CarbonImmutable::parse('2000-01-01 01:02:03.456789'));
    ignoreMigrationQueries();

    Config::set('queue.default', 'database');
});

it('ingests processed job attempts', function () {
    ProcessedJob::dispatch();

    Artisan::call('queue:work', ['--max-jobs' => 1, '--stop-when-empty' => true, '--sleep' => 0]);

    $this->ingest->assertWrittenTimes(1);
    $this->ingest->assertLatestWrite('job-attempt:*', [
        [
            'v' => 1,
            't' => 'job-attempt',
            'timestamp' => 946688523.456789,
            'deploy' => 'v1.2.3',
            'server' => 'web-01',
            '_group' => md5('ProcessedJob'),
            'trace_id' => $this->traceId,
            'user' => '',
            'job_id' => $this->jobId,
            'attempt_id' => $this->attemptId,
            'attempt' => 1,
            'name' => 'ProcessedJob',
            'connection' => 'database',
            'queue' => 'default',
            'status' => 'processed',
            'duration' => 2500,
            'exceptions' => 0,
            'logs' => 0,
            'queries' => 2, // select and delete against the jobs table
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
    ReleasedJob::dispatch();

    Artisan::call('queue:work', ['--max-jobs' => 1, '--stop-when-empty' => true, '--sleep' => 0]);

    $this->ingest->assertWrittenTimes(1);
    $this->ingest->assertLatestWrite('job-attempt:*', [
        [
            'v' => 1,
            't' => 'job-attempt',
            'timestamp' => 946688523.456789,
            'deploy' => 'v1.2.3',
            'server' => 'web-01',
            '_group' => md5('ReleasedJob'),
            'trace_id' => $this->traceId,
            'user' => '',
            'job_id' => $this->jobId,
            'attempt_id' => $this->attemptId,
            'attempt' => 1,
            'name' => 'ReleasedJob',
            'connection' => 'database',
            'queue' => 'default',
            'status' => 'released',
            'duration' => 2500,
            'exceptions' => 0,
            'logs' => 0,
            'queries' => 3, // select, delete, and insert against the jobs table
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
    FailedJob::dispatch();

    Artisan::call('queue:work', ['--max-jobs' => 1, '--stop-when-empty' => true, '--sleep' => 0]);

    $this->ingest->assertWrittenTimes(1);
    $this->ingest->assertLatestWrite('job-attempt:*', [
        [
            'v' => 1,
            't' => 'job-attempt',
            'timestamp' => 946688523.456789,
            'deploy' => 'v1.2.3',
            'server' => 'web-01',
            '_group' => md5('FailedJob'),
            'trace_id' => $this->traceId,
            'user' => '',
            'job_id' => $this->jobId,
            'attempt_id' => $this->attemptId,
            'attempt' => 1,
            'name' => 'FailedJob',
            'connection' => 'database',
            'queue' => 'default',
            'status' => 'failed',
            'duration' => 2500,
            'exceptions' => 1, // TODO: `exceptions` not incremented because JobAttemptedListener is called before ExceptionSensor
            'logs' => 0,
            'queries' => 2, // select and delete against the jobs table
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

it('does not ingest jobs dispatched on the sync queue', function () {
    ProcessedJob::dispatchSync();

    $this->ingest->assertWrittenTimes(0);
});

it('captures closure job', function () {
    dispatch(function () {
        travelTo(now()->addMicroseconds(2500));
    });

    Artisan::call('queue:work', ['--max-jobs' => 1, '--stop-when-empty' => true, '--sleep' => 0]);

    $this->ingest->assertWrittenTimes(1);
    $this->ingest->assertLatestWrite('job-attempt:*', [
        [
            'v' => 1,
            't' => 'job-attempt',
            'timestamp' => 946688523.456789,
            'deploy' => 'v1.2.3',
            'server' => 'web-01',
            '_group' => md5('Closure (JobAttemptSensorTest.php:176)'),
            'trace_id' => $this->traceId,
            'user' => '',
            'job_id' => $this->jobId,
            'attempt_id' => $this->attemptId,
            'attempt' => 1,
            'name' => 'Closure (JobAttemptSensorTest.php:176)',
            'connection' => 'database',
            'queue' => 'default',
            'status' => 'processed',
            'duration' => 2500,
            'exceptions' => 0,
            'logs' => 0,
            'queries' => 2, // select and delete against the jobs table
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
    Event::listen(MyEvent::class, MyEventListener::class);
    Event::dispatch(new MyEvent);

    Artisan::call('queue:work', ['--max-jobs' => 1, '--stop-when-empty' => true, '--sleep' => 0]);

    $this->ingest->assertWrittenTimes(1);
    $this->ingest->assertLatestWrite('job-attempt:*', [
        [
            'v' => 1,
            't' => 'job-attempt',
            'timestamp' => 946688523.456789,
            'deploy' => 'v1.2.3',
            'server' => 'web-01',
            '_group' => md5('MyEventListener'),
            'trace_id' => $this->traceId,
            'user' => '',
            'job_id' => $this->jobId,
            'attempt_id' => $this->attemptId,
            'attempt' => 1,
            'name' => 'MyEventListener',
            'connection' => 'database',
            'queue' => 'default',
            'status' => 'processed',
            'duration' => 2500,
            'exceptions' => 0,
            'logs' => 0,
            'queries' => 2, // select and delete against the jobs table
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
    Config::set('mail.default', 'log');

    Mail::to('tim@laravel.com')->queue(new MyQueuedMail);

    Artisan::call('queue:work', ['--max-jobs' => 1, '--stop-when-empty' => true, '--sleep' => 0]);

    $this->ingest->assertWrittenTimes(1);
    $this->ingest->assertLatestWrite('job-attempt:*', [
        [
            'v' => 1,
            't' => 'job-attempt',
            'timestamp' => 946688523.456789,
            'deploy' => 'v1.2.3',
            'server' => 'web-01',
            '_group' => md5('MyQueuedMail'),
            'trace_id' => $this->traceId,
            'user' => '',
            'job_id' => $this->jobId,
            'attempt_id' => $this->attemptId,
            'attempt' => 1,
            'name' => 'MyQueuedMail',
            'connection' => 'database',
            'queue' => 'default',
            'status' => 'processed',
            'duration' => 2500,
            'exceptions' => 0,
            'logs' => 0,
            'queries' => 2, // select and delete against the jobs table
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
    $this->ingest->assertLatestWrite('mail:*', [
        [
            'v' => 1,
            't' => 'mail',
            'timestamp' => 946688523.459289,
            'deploy' => 'v1.2.3',
            'server' => 'web-01',
            '_group' => md5('MyQueuedMail'),
            'trace_id' => $this->traceId,
            'execution_source' => 'job',
            'execution_id' => $this->executionId,
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
    FailedJob::dispatch();

    Artisan::call('queue:work', ['--max-jobs' => 2, '--tries' => 2, '--stop-when-empty' => true, '--sleep' => 0]);

    $this->ingest->assertWrittenTimes(2);
    $this->ingest->assertLatestWrite('job-attempt:0.attempt', 2);
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
