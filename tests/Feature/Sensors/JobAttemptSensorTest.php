<?php

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Laravel\Nightwatch\Types\Str;
use Illuminate\Foundation\Testing\WithConsoleEvents;

use function Orchestra\Testbench\Pest\defineEnvironment;
use function Pest\Laravel\travelTo;

uses(WithConsoleEvents::class);

defineEnvironment(function () {
    forceCommandExecutionState();
    Str::createUuidsUsingSequence([
        $this->traceId = '0d3ca349-e222-4982-ac23-2343692de258',
        $this->jobId = 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        $this->executionId = '3f45e448-0901-4782-aaec-5ec37305f442',
        $this->attemptId = '02cb9091-89 73-427f-8d3f-042f2ec4e862',
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

// Ensure the following
// 1. The trace id is set from the hidden context
// 2. The source is set to 'job' in child records
// 2. The attempt id is generated
// 3. The records are written to the ingest
// 4. The duration is captured in microseconds
// 5. The command state is reset on each job attempt
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

it('ingests job failed job attempts')->todo();

it('does not ingest jobs dispatched on the sync queue')->todo();

it('normalizes sqs queue names')->todo();

it('captures duration in microseconds')->todo();

it('captures closure job')->todo();

it('captures queued event listener')->todo();

it('captures queued mail')->todo();

it('captures queued notification')->todo();

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
        throw new RuntimeException('Job failed');
    }
}
