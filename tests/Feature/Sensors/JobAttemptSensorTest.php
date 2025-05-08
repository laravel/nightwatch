<?php

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Testing\WithConsoleEvents;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Compatibility;

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
    Config::set('cache.default', 'array');
    Config::set('queue.default', 'database');
    Redis::command('FLUSHALL');
});

$workCommands = [
    'queue:work',
    'horizon:work',
];

$workOptions = [
    '--max-jobs' => 2, // Loop twice as job attempts are ingested in the next loop.
    '--sleep' => 0,
    '--stop-when-empty' => true,
    '--tries' => 1,
];

it('ingests processed job attempts', function ($workCommand) use ($workOptions) {
    $ingest = fakeIngest();
    Str::createUuidsUsingSequence([
        $jobId = 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        $attemptId = '02cb9091-8973-427f-8d3f-042f2ec4e862',
    ]);
    ProcessedJob::dispatch();

    nightwatch()->state->records->flush();

    Artisan::call($workCommand, $workOptions);

    // 3 writes: 2 for the `Looping` event, 1 for the `WorkerStopping` event.
    $ingest->assertWrittenTimes(3);
    $ingest->assertWrite(1, 'job-attempt:*', [
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
            'exception_preview' => '',
        ],
    ]);
})->with($workCommands);

it('ingests released job attempts', function ($workCommand) use ($workOptions) {
    $ingest = fakeIngest();
    Str::createUuidsUsingSequence([
        $jobId = 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        $attemptId = '02cb9091-8973-427f-8d3f-042f2ec4e862',
    ]);
    FailedJob::dispatch();
    nightwatch()->state->records->flush();

    Artisan::call($workCommand, [...$workOptions, '--tries' => 2]);

    $ingest->assertWrittenTimes(3);
    $ingest->assertWrite(1, 'job-attempt:*', [
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
            'status' => 'released',
            'duration' => 2500,
            'exceptions' => 1,
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
            'exception_preview' => 'Job failed',
        ],
    ]);
    $ingest->assertWrite(1, 'exception:0.execution_source', 'job');
    $ingest->assertWrite(1, 'exception:0.execution_id', $attemptId);

    forgetRecordedExceptions(1);
})->with($workCommands);

it('ingests manually released job attempts', function ($workCommand) use ($workOptions) {
    $ingest = fakeIngest();
    Str::createUuidsUsingSequence([
        $jobId = 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        $attemptId = '02cb9091-8973-427f-8d3f-042f2ec4e862',
    ]);
    ReleasedJob::dispatch();
    nightwatch()->state->records->flush();

    Artisan::call($workCommand, [...$workOptions, '--tries' => 2]);

    $ingest->assertWrittenTimes(3);
    $ingest->assertWrite(1, 'job-attempt:*', [
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
            'exception_preview' => '',
        ],
    ]);
})->with($workCommands);

it('ingests job failed job attempts', function ($workCommand) use ($workOptions) {
    $ingest = fakeIngest();
    Str::createUuidsUsingSequence([
        $jobId = 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        $attemptId = '02cb9091-8973-427f-8d3f-042f2ec4e862',
    ]);
    FailedJob::dispatch();
    nightwatch()->state->records->flush();

    Artisan::call($workCommand, $workOptions);

    $ingest->assertWrittenTimes(3);
    $ingest->assertWrite(1, 'job-attempt:*', [
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
            'queries' => 5, // Reserve and delete the job, and insert into the failed_jobs table
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
            'exception_preview' => 'Job failed',
        ],
    ]);
    $ingest->assertWrite(1, 'exception:0.execution_source', 'job');
    $ingest->assertWrite(1, 'exception:0.execution_id', $attemptId);
})->with($workCommands);

it('does not ingest jobs dispatched on the sync queue', function () {
    $ingest = fakeIngest();
    ProcessedJob::dispatchSync();

    $ingest->assertWrittenTimes(0);
});

it('captures closure job', function ($workCommand) use ($workOptions) {
    $ingest = fakeIngest();
    Str::createUuidsUsingSequence([
        $jobId = 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        $attemptId = '02cb9091-8973-427f-8d3f-042f2ec4e862',
    ]);
    $line = __LINE__ + 1;
    dispatch(function () {
        travelTo(now()->addMicroseconds(2500));
    });
    nightwatch()->state->records->flush();

    Artisan::call($workCommand, $workOptions);

    $ingest->assertWrittenTimes(3);
    $ingest->assertWrite(1, 'job-attempt:*', [
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
            'exception_preview' => '',
        ],
    ]);
})->with($workCommands);

it('captures queued event listener', function ($workCommand) use ($workOptions) {
    $ingest = fakeIngest();
    Str::createUuidsUsingSequence([
        $jobId = 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        $attemptId = '02cb9091-8973-427f-8d3f-042f2ec4e862',
    ]);
    Event::listen(MyEvent::class, MyEventListener::class);
    Event::dispatch(new MyEvent);
    nightwatch()->state->records->flush();

    Artisan::call($workCommand, $workOptions);

    $ingest->assertWrittenTimes(3);
    $ingest->assertWrite(1, 'job-attempt:*', [
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
            'exception_preview' => '',
        ],
    ]);
})->with($workCommands);

it('captures queued mail', function ($workCommand) use ($workOptions) {
    $ingest = fakeIngest();
    Str::createUuidsUsingSequence([
        $jobId = 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        $attemptId = '02cb9091-8973-427f-8d3f-042f2ec4e862',
    ]);
    Config::set('mail.default', 'log');
    Mail::to('tim@laravel.com')->queue(new MyQueuedMail);
    nightwatch()->state->records->flush();

    Artisan::call($workCommand, $workOptions);

    $ingest->assertWrittenTimes(3);
    $ingest->assertWrite(1, 'job-attempt:*', [
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
            'exception_preview' => '',
        ],
    ]);
    $ingest->assertWrite(1, 'mail:*', [
        [
            'v' => 1,
            't' => 'mail',
            'timestamp' => 946688523.459289,
            'deploy' => 'v1.2.3',
            'server' => 'web-01',
            '_group' => Compatibility::$mailableClassNameCapturable ? hash('xxh128', 'MyQueuedMail') : hash('xxh128', ''),
            'trace_id' => '0d3ca349-e222-4982-ac23-2343692de258',
            'execution_source' => 'job',
            'execution_id' => $attemptId,
            'execution_preview' => 'MyQueuedMail',
            'execution_stage' => 'action',
            'user' => '',
            'mailer' => 'log',
            'class' => Compatibility::$mailableClassNameCapturable ? 'MyQueuedMail' : '',
            'subject' => 'My Queued Mail',
            'to' => 1,
            'cc' => 0,
            'bcc' => 0,
            'attachments' => 0,
            'duration' => 0,
            'failed' => false,
        ],
    ]);
})->with($workCommands);

it('captures multiple job attempts', function ($workCommand) use ($workOptions) {
    $ingest = fakeIngest();
    FailedJob::dispatch();
    nightwatch()->state->records->flush();

    Artisan::call($workCommand, [...$workOptions, '--max-jobs' => 3, '--tries' => 2]);

    $ingest->assertWrittenTimes(4);
    $ingest->assertWrite(1, 'job-attempt:0.attempt', 1);
    $ingest->assertWrite(1, 'exception:0.message', 'Job failed');
    $ingest->assertWrite(2, 'job-attempt:0.attempt', 2);
    $ingest->assertWrite(2, 'exception:0.message', 'Job failed');
})->with($workCommands);

it('captures manually reported exceptions', function ($workCommand) use ($workOptions) {
    $ingest = fakeIngest();
    Str::createUuidsUsingSequence([
        $jobId = 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        $attemptId = '02cb9091-8973-427f-8d3f-042f2ec4e862',
    ]);
    $line = __LINE__ + 1;
    dispatch(function () {
        travelTo(now()->addMicroseconds(2500));

        report('Whoops!');
    });
    nightwatch()->state->records->flush();

    Artisan::call($workCommand, $workOptions);

    $ingest->assertWrittenTimes(3);
    $ingest->assertWrite(1, 'job-attempt:*', [
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
            'exceptions' => 1,
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
            'exception_preview' => '',
        ],
    ]);
    $ingest->assertWrite(1, 'exception:0.message', 'Whoops!');
})->with($workCommands);

final class ProcessedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        travelTo(now()->addMicroseconds(2500));
    }
}

final class ReleasedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        travelTo(now()->addMicroseconds(2500));

        $this->release();
    }
}

final class FailedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
