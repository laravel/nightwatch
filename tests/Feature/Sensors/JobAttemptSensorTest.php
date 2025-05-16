<?php

namespace Tests\Feature\Sensors;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Testing\WithConsoleEvents;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Compatibility;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

use function dispatch;
use function expect;
use function hash;
use function now;
use function report;

class JobAttemptSensorTest extends TestCase
{
    use WithConsoleEvents;

    protected function setUp(): void
    {
        $this->forceCommandExecutionState();

        parent::setUp();

        $this->setDeploy('v1.2.3');
        $this->setServerName('web-01');
        $this->setPeakMemory(1234);
        $this->setTraceId('0d3ca349-e222-4982-ac23-2343692de258');
        $this->setExecutionStart(CarbonImmutable::parse('2000-01-01 01:02:03.456789'));
        // --- //
        Redis::command('FLUSHALL');
    }

    #[DataProvider('workCommands')]
    public function test_it_ingests_processed_job_attempts($workCommand)
    {
        $ingest = $this->fakeIngest();
        Str::createUuidsUsingSequence([
            $jobId = 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
            $attemptId = '02cb9091-8973-427f-8d3f-042f2ec4e862',
        ]);
        ProcessedJob::dispatch();

        Artisan::call($workCommand, $this->workOptions());

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('job-attempt:*', [
            [
                'v' => 1,
                't' => 'job-attempt',
                'timestamp' => 946688523.456789,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => hash('xxh128', 'Tests\Feature\Sensors\ProcessedJob'),
                'trace_id' => '0d3ca349-e222-4982-ac23-2343692de258',
                'user' => '',
                'job_id' => $jobId,
                'attempt_id' => $attemptId,
                'attempt' => 1,
                'name' => 'Tests\Feature\Sensors\ProcessedJob',
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
                'cache_events' => 1,
                'hydrated_models' => 0,
                'peak_memory_usage' => 1234,
                'exception_preview' => '',
            ],
        ]);
    }

    #[DataProvider('workCommands')]
    public function test_it_ingests_released_job_attempts($workCommand)
    {
        $ingest = $this->fakeIngest();
        Str::createUuidsUsingSequence([
            $jobId = 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
            $attemptId = '02cb9091-8973-427f-8d3f-042f2ec4e862',
        ]);
        FailedJob::dispatch();

        Artisan::call($workCommand, $this->workOptions(['--tries' => 2]));

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('job-attempt:*', [
            [
                'v' => 1,
                't' => 'job-attempt',
                'timestamp' => 946688523.456789,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => hash('xxh128', 'Tests\Feature\Sensors\FailedJob'),
                'trace_id' => '0d3ca349-e222-4982-ac23-2343692de258',
                'user' => '',
                'job_id' => $jobId,
                'attempt_id' => $attemptId,
                'attempt' => 1,
                'name' => 'Tests\Feature\Sensors\FailedJob',
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
                'cache_events' => 1,
                'hydrated_models' => 0,
                'peak_memory_usage' => 1234,
                'exception_preview' => 'Job failed',
            ],
        ]);
    }

    #[DataProvider('workCommands')]
    public function test_it_ingests_manually_released_job_attempts($workCommand)
    {
        $ingest = $this->fakeIngest();
        Str::createUuidsUsingSequence([
            $jobId = 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
            $attemptId = '02cb9091-8973-427f-8d3f-042f2ec4e862',
        ]);
        ReleasedJob::dispatch();

        Artisan::call($workCommand, $this->workOptions());

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('job-attempt:*', [
            [
                'v' => 1,
                't' => 'job-attempt',
                'timestamp' => 946688523.456789,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => hash('xxh128', 'Tests\Feature\Sensors\ReleasedJob'),
                'trace_id' => '0d3ca349-e222-4982-ac23-2343692de258',
                'user' => '',
                'job_id' => $jobId,
                'attempt_id' => $attemptId,
                'attempt' => 1,
                'name' => 'Tests\Feature\Sensors\ReleasedJob',
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
                'cache_events' => 1,
                'hydrated_models' => 0,
                'peak_memory_usage' => 1234,
                'exception_preview' => '',
            ],
        ]);
    }

    #[DataProvider('workCommands')]
    public function test_it_ingests_job_failed_job_attempts($workCommand)
    {
        $ingest = $this->fakeIngest();
        Str::createUuidsUsingSequence([
            $jobId = 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
            $attemptId = '02cb9091-8973-427f-8d3f-042f2ec4e862',
        ]);
        FailedJob::dispatch();

        Artisan::call($workCommand, $this->workOptions());

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('job-attempt:*', [
            [
                'v' => 1,
                't' => 'job-attempt',
                'timestamp' => 946688523.456789,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => hash('xxh128', 'Tests\Feature\Sensors\FailedJob'),
                'trace_id' => '0d3ca349-e222-4982-ac23-2343692de258',
                'user' => '',
                'job_id' => $jobId,
                'attempt_id' => $attemptId,
                'attempt' => 1,
                'name' => 'Tests\Feature\Sensors\FailedJob',
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
                'cache_events' => 1,
                'hydrated_models' => 0,
                'peak_memory_usage' => 1234,
                'exception_preview' => 'Job failed',
            ],
        ]);
    }

    public function test_it_does_not_ingest_jobs_dispatched_on_the_sync_queue()
    {
        $ingest = $this->fakeIngest();
        ProcessedJob::dispatchSync();

        $ingest->assertWrittenTimes(0);
    }

    #[DataProvider('workCommands')]
    public function test_it_captures_closure_job($workCommand)
    {
        $ingest = $this->fakeIngest();
        Str::createUuidsUsingSequence([
            $jobId = 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
            $attemptId = '02cb9091-8973-427f-8d3f-042f2ec4e862',
        ]);
        $line = __LINE__ + 1;
        dispatch(function () {
            Date::setTestNow(now()->addMicroseconds(2500));
        });

        Artisan::call($workCommand, $this->workOptions());

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
                'cache_events' => 1,
                'hydrated_models' => 0,
                'peak_memory_usage' => 1234,
                'exception_preview' => '',
            ],
        ]);
    }

    #[DataProvider('workCommands')]
    public function test_it_captures_queued_event_listener($workCommand)
    {
        $ingest = $this->fakeIngest();
        Str::createUuidsUsingSequence([
            $jobId = 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
            $attemptId = '02cb9091-8973-427f-8d3f-042f2ec4e862',
        ]);
        Event::listen(MyJobAttemptEvent::class, MyEventListener::class);
        Event::dispatch(new MyJobAttemptEvent);

        Artisan::call($workCommand, $this->workOptions());

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('job-attempt:*', [
            [
                'v' => 1,
                't' => 'job-attempt',
                'timestamp' => 946688523.456789,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => hash('xxh128', 'Tests\Feature\Sensors\MyEventListener'),
                'trace_id' => '0d3ca349-e222-4982-ac23-2343692de258',
                'user' => '',
                'job_id' => $jobId,
                'attempt_id' => $attemptId,
                'attempt' => 1,
                'name' => 'Tests\Feature\Sensors\MyEventListener',
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
                'cache_events' => 1,
                'hydrated_models' => 0,
                'peak_memory_usage' => 1234,
                'exception_preview' => '',
            ],
        ]);
    }

    #[DataProvider('workCommands')]
    public function test_it_captures_queued_mail($workCommand)
    {
        $ingest = $this->fakeIngest();
        Str::createUuidsUsingSequence([
            $jobId = 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
            $attemptId = '02cb9091-8973-427f-8d3f-042f2ec4e862',
        ]);
        Mail::to('tim@laravel.com')->queue(new JobAttemptMail);

        Artisan::call($workCommand, $this->workOptions());

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('job-attempt:*', [
            [
                'v' => 1,
                't' => 'job-attempt',
                'timestamp' => 946688523.456789,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => hash('xxh128', 'Tests\Feature\Sensors\JobAttemptMail'),
                'trace_id' => '0d3ca349-e222-4982-ac23-2343692de258',
                'user' => '',
                'job_id' => $jobId,
                'attempt_id' => $attemptId,
                'attempt' => 1,
                'name' => 'Tests\Feature\Sensors\JobAttemptMail',
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
                'cache_events' => 1,
                'hydrated_models' => 0,
                'peak_memory_usage' => 1234,
                'exception_preview' => '',
            ],
        ]);
        $ingest->assertLatestWrite('mail:*', [
            [
                'v' => 1,
                't' => 'mail',
                'timestamp' => 946688523.459289,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => Compatibility::$mailableClassNameCapturable ? hash('xxh128', 'Tests\Feature\Sensors\JobAttemptMail') : hash('xxh128', ''),
                'trace_id' => '0d3ca349-e222-4982-ac23-2343692de258',
                'execution_source' => 'job',
                'execution_id' => $attemptId,
                'execution_preview' => 'Tests\Feature\Sensors\JobAttemptMail',
                'execution_stage' => 'action',
                'user' => '',
                'mailer' => 'array',
                'class' => Compatibility::$mailableClassNameCapturable ? 'Tests\Feature\Sensors\JobAttemptMail' : '',
                'subject' => 'Job Attempt Mail',
                'to' => 1,
                'cc' => 0,
                'bcc' => 0,
                'attachments' => 0,
                'duration' => 0,
                'failed' => false,
            ],
        ]);
    }

    #[DataProvider('workCommands')]
    public function test_it_captures_multiple_job_attempts($workCommand)
    {
        $ingest = $this->fakeIngest();
        FailedJob::dispatch();

        Artisan::call($workCommand, $this->workOptions(['--max-jobs' => 2, '--tries' => 2]));

        $ingest->assertWrittenTimes(2);
        $ingest->assertWrite(0, 'job-attempt:0.attempt', 1);
        $ingest->assertWrite(0, 'exception:0.message', 'Job failed');
        $ingest->assertWrite(1, 'job-attempt:0.attempt', 2);
        $ingest->assertWrite(1, 'exception:0.message', 'Job failed');
    }

    #[DataProvider('workCommands')]
    public function test_it_captures_manually_reported_exceptions($workCommand)
    {
        $ingest = $this->fakeIngest();
        Str::createUuidsUsingSequence([
            $jobId = 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
            $attemptId = '02cb9091-8973-427f-8d3f-042f2ec4e862',
        ]);
        $line = __LINE__ + 1;
        dispatch(function () {
            Date::setTestNow(now()->addMicroseconds(2500));

            report('Whoops!');
        });

        Artisan::call($workCommand, $this->workOptions());

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
                'cache_events' => 1,
                'hydrated_models' => 0,
                'peak_memory_usage' => 1234,
                'exception_preview' => '',
            ],
        ]);
        $ingest->assertLatestWrite('exception:0', function ($exception) use ($line) {
            expect($exception)->toMatchArray([
                'message' => 'Whoops!',
                'handled' => true,
                'trace_id' => '0d3ca349-e222-4982-ac23-2343692de258',
                'execution_source' => 'job',
                'execution_id' => '02cb9091-8973-427f-8d3f-042f2ec4e862',
                'execution_preview' => "Closure (JobAttemptSensorTest.php:{$line})",
                'execution_stage' => 'action',
            ]);

            return true;
        });
    }

    #[DataProvider('workCommands')]
    public function test_it_resets_the_state_between_job_attempts($workCommand)
    {
        $ingest = $this->fakeIngest();

        FailedJob::dispatch();
        ProcessedJob::dispatch();

        Artisan::call($workCommand, $this->workOptions(['--max-jobs' => 2]));

        $ingest->assertWrittenTimes(2);
        $ingest->assertWrite(0, 'job-attempt:0.exception_preview', 'Job failed');
        $ingest->assertWrite(1, 'job-attempt:0.exception_preview', '');
    }

    #[DataProvider('workCommands')]
    public function test_it_does_not_ingest_or_build_up_state_while_idle($workCommand)
    {
        $ingest = $this->fakeIngest();
        $loops = 0;
        Queue::looping(function () use (&$loops) {
            $loops++;
        });
        Artisan::call($workCommand, ['--max-time' => 0.05, '--sleep' => 0]);

        expect($loops)->toBeGreaterThan(50);
        $ingest->assertWrittenTimes(0);
        expect($this->core->ingest->buffer)->toHaveCount(2); // popping query + illuminate:queue:restart
    }

    #[DataProvider('workCommands')]
    public function test_it_captures_all_queue_events_for_a_job($workCommand)
    {
        $ingest = $this->fakeIngest();
        Str::createUuidsUsingSequence([
            $jobId = 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
            $attemptId = '02cb9091-8973-427f-8d3f-042f2ec4e862',
        ]);
        $this->prependListener(QueryExecuted::class, function (QueryExecuted $event) {
            $event->time = 1;

            $this->travelTo(now()->addMicroseconds(1000));
        });
        ProcessedJob::dispatch();

        Artisan::call($workCommand, $this->workOptions());

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($write) {
            expect($write)->toHaveCount(6);
            expect($write[0])->toMatchArray([
                't' => 'query',
                'sql' => 'select * from "jobs" where "queue" = ? and (("reserved_at" is null and "available_at" <= ?) or ("reserved_at" <= ?)) order by "id" asc limit 1',
                'trace_id' => '0d3ca349-e222-4982-ac23-2343692de258',
                'execution_id' => '02cb9091-8973-427f-8d3f-042f2ec4e862',
                'execution_source' => 'job',
                'execution_stage' => 'action',
                'execution_preview' => 'Tests\Feature\Sensors\ProcessedJob',
            ]);
            expect($write[1])->toMatchArray([
                't' => 'query',
                'sql' => 'update "jobs" set "reserved_at" = ?, "attempts" = ? where "id" = ?',
                'trace_id' => '0d3ca349-e222-4982-ac23-2343692de258',
                'execution_id' => '02cb9091-8973-427f-8d3f-042f2ec4e862',
                'execution_source' => 'job',
                'execution_stage' => 'action',
                'execution_preview' => 'Tests\Feature\Sensors\ProcessedJob',
            ]);
            expect($write[2])->toMatchArray([
                't' => 'query',
                'sql' => 'select * from "jobs" where "id" = ? limit 1',
                'trace_id' => '0d3ca349-e222-4982-ac23-2343692de258',
                'execution_id' => '02cb9091-8973-427f-8d3f-042f2ec4e862',
                'execution_source' => 'job',
                'execution_stage' => 'action',
                'execution_preview' => 'Tests\Feature\Sensors\ProcessedJob',
            ]);
            expect($write[3])->toMatchArray([
                't' => 'query',
                'sql' => 'delete from "jobs" where "id" = ?',
                'trace_id' => '0d3ca349-e222-4982-ac23-2343692de258',
                'execution_id' => '02cb9091-8973-427f-8d3f-042f2ec4e862',
                'execution_source' => 'job',
                'execution_stage' => 'action',
                'execution_preview' => 'Tests\Feature\Sensors\ProcessedJob',
            ]);
            expect($write[4])->toMatchArray([
                't' => 'job-attempt',
                'name' => 'Tests\Feature\Sensors\ProcessedJob',
                'trace_id' => '0d3ca349-e222-4982-ac23-2343692de258',
            ]);
            expect($write[5])->toMatchArray([
                't' => 'cache-event',
                'key' => 'illuminate:queue:restart',
                'trace_id' => '0d3ca349-e222-4982-ac23-2343692de258',
                'execution_id' => '02cb9091-8973-427f-8d3f-042f2ec4e862',
                'execution_source' => 'job',
                'execution_stage' => 'action',
                'execution_preview' => 'Tests\Feature\Sensors\ProcessedJob',
                'trace_id' => '0d3ca349-e222-4982-ac23-2343692de258',
            ]);

            return true;
        });
    }

    #[DataProvider('workCommands')]
    public function test_it_captures_counts_occuring_outside_job_execution($workCommand)
    {
        $ingest = $this->fakeIngest();
        Str::createUuidsUsingSequence([
            $jobId = 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
            $attemptId = '02cb9091-8973-427f-8d3f-042f2ec4e862',
        ]);
        Http::fake(['https://laravel.com' => Http::response()]);
        Event::listen(function (CacheMissed $event) {
            if ($event->key !== 'illuminate:queue:restart') {
                return;
            }

            Http::get('https://laravel.com');
        });

        ProcessedJob::dispatch();
        Artisan::call($workCommand, $this->workOptions());

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($write) {
            expect($write)->toHaveCount(7);
            expect($write[4])->toMatchArray([
                't' => 'job-attempt',
                'outgoing_requests' => 1,
            ]);
            expect($write[6])->toMatchArray([
                't' => 'outgoing-request',
            ]);

            return true;
        });
    }

    protected function workOptions(array $overrides = []): array
    {
        return [
            '--max-jobs' => 1,
            '--sleep' => 0,
            '--stop-when-empty' => true,
            '--tries' => 1,
            ...$overrides,
        ];
    }

    public static function workCommands(): iterable
    {
        yield ['queue:work'];
        yield ['horizon:work'];
    }
}

final class ProcessedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        Date::setTestNow(now()->addMicroseconds(2500));
    }
}

final class ReleasedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        Date::setTestNow(now()->addMicroseconds(2500));

        $this->release();
    }
}

final class FailedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        Date::setTestNow(now()->addMicroseconds(2500));

        throw new RuntimeException('Job failed');
    }
}

final class ExceptionReportingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        Date::setTestNow(now()->addMicroseconds(2500));

        report(new RuntimeException('Whoops!'));
    }
}

final class MyEventListener implements ShouldQueue
{
    public function handle()
    {
        Date::setTestNow(now()->addMicroseconds(2500));
    }
}

class MyJobAttemptEvent
{
    use Dispatchable;
}

class JobAttemptMail extends Mailable
{
    public function content(): Content
    {
        Date::setTestNow(now()->addMicroseconds(2500));

        return new Content(
            view: 'mail',
        );
    }
}
