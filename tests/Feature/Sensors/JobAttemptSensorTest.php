<?php

namespace Tests\Feature\Sensors;

use Aws\Result;
use Aws\Sqs\SqsClient;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Testing\WithConsoleEvents;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Queue\CallQueuedClosure;
use Illuminate\Queue\Connectors\DatabaseConnector;
use Illuminate\Queue\Connectors\SqsConnector;
use Illuminate\Queue\DatabaseQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\DatabaseJob;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\SqsQueue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Compatibility;
use Laravel\Vapor\Console\Commands\VaporWorkCommand;
use Laravel\Vapor\Events\LambdaEvent;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

use function app;
use function array_keys;
use function dispatch;
use function hash;
use function json_encode;
use function now;
use function putenv;
use function report;
use function serialize;

class JobAttemptSensorTest extends TestCase
{
    use WithConsoleEvents;

    protected $isVapor = false;

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

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->isVapor) {
            putenv('VAPOR_SSM_PATH');

            VaporWorkCommand::flushState();
        }
    }

    protected function setupVaporEnvironment(): void
    {
        putenv('VAPOR_SSM_PATH=/vapor');

        $mockSqsClient = Mockery::mock(SqsClient::class);
        $mockSqsClient->allows('deleteMessage')->andReturn(new Result(['MessageId' => 'test-message-id']));
        $mockSqsClient->allows('sendMessage')->andReturn(new Result(['MessageId' => 'test-message-id']));
        $mockSqsClient->allows('changeMessageVisibility')->andReturn(new Result(['MessageId' => 'test-message-id']));

        $mockSqsConnector = Mockery::mock(SqsConnector::class);
        $mockSqsConnector->shouldReceive('connect')
            ->andReturn(new SqsQueue($mockSqsClient, 'default'));

        $this->app['queue']->extend('sqs', fn () => $mockSqsConnector);
    }

    #[DataProvider('workCommands')]
    public function test_it_ingests_processed_job_attempts($workCommand): void
    {
        $this->isVapor = $workCommand === 'vapor:work';

        $ingest = $this->fakeIngest();
        Str::createUuidsUsingSequence([
            $jobId = 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
            $attemptId = '02cb9091-8973-427f-8d3f-042f2ec4e862',
        ]);

        if ($this->isVapor) {
            $this->setupVaporEnvironment();
            $this->bindLambdaEventForJob(new ProcessedJob);
        } else {
            ProcessedJob::dispatch();
        }

        Artisan::call($workCommand, $this->workOptions($workCommand));

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
                'connection' => $this->isVapor ? 'sqs' : 'database',
                'queue' => 'default',
                'status' => 'processed',
                'duration' => 2500,
                'exceptions' => 0,
                'logs' => 0,
                'queries' => $this->isVapor ? 0 : 4,
                'lazy_loads' => 0,
                'jobs_queued' => 0,
                'mail' => 0,
                'notifications' => 0,
                'outgoing_requests' => 0,
                'files_read' => 0,
                'files_written' => 0,
                'cache_events' => $this->isVapor ? 0 : 1,
                'hydrated_models' => 0,
                'peak_memory_usage' => 1234,
                'exception_preview' => '',
            ],
        ]);
    }

    #[DataProvider('workCommands')]
    public function test_it_ingests_released_job_attempts($workCommand): void
    {
        $this->isVapor = $workCommand === 'vapor:work';

        $ingest = $this->fakeIngest();
        Str::createUuidsUsingSequence([
            $jobId = 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
            $attemptId = '02cb9091-8973-427f-8d3f-042f2ec4e862',
        ]);

        if ($this->isVapor) {
            $this->setupVaporEnvironment();
            $this->bindLambdaEventForJob(new FailedJob);
        } else {
            FailedJob::dispatch();
        }

        Artisan::call($workCommand, $this->workOptions($workCommand, ['--tries' => 2]));

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
                'connection' => $this->isVapor ? 'sqs' : 'database',
                'queue' => 'default',
                'status' => 'released',
                'duration' => 2500,
                'exceptions' => 1,
                'logs' => 0,
                'queries' => $this->isVapor ? 0 : 5,
                'lazy_loads' => 0,
                'jobs_queued' => 0,
                'mail' => 0,
                'notifications' => 0,
                'outgoing_requests' => 0,
                'files_read' => 0,
                'files_written' => 0,
                'cache_events' => $this->isVapor ? 0 : 1,
                'hydrated_models' => 0,
                'peak_memory_usage' => 1234,
                'exception_preview' => 'Job failed',
            ],
        ]);
    }

    #[DataProvider('workCommands')]
    public function test_it_ingests_manually_released_job_attempts($workCommand): void
    {
        $this->isVapor = $workCommand === 'vapor:work';

        $ingest = $this->fakeIngest();
        Str::createUuidsUsingSequence([
            $jobId = 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
            $attemptId = '02cb9091-8973-427f-8d3f-042f2ec4e862',
        ]);

        if ($this->isVapor) {
            $this->setupVaporEnvironment();
            $this->bindLambdaEventForJob(new ReleasedJob);
        } else {
            ReleasedJob::dispatch();
        }

        Artisan::call($workCommand, $this->workOptions($workCommand));

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
                'connection' => $this->isVapor ? 'sqs' : 'database',
                'queue' => 'default',
                'status' => 'released',
                'duration' => 2500,
                'exceptions' => 0,
                'logs' => 0,
                'queries' => $this->isVapor ? 0 : 5,
                'lazy_loads' => 0,
                'jobs_queued' => 0,
                'mail' => 0,
                'notifications' => 0,
                'outgoing_requests' => 0,
                'files_read' => 0,
                'files_written' => 0,
                'cache_events' => $this->isVapor ? 0 : 1,
                'hydrated_models' => 0,
                'peak_memory_usage' => 1234,
                'exception_preview' => '',
            ],
        ]);
    }

    #[DataProvider('workCommands')]
    public function test_it_ingests_failed_job_attempts($workCommand): void
    {
        $this->isVapor = $workCommand === 'vapor:work';

        $ingest = $this->fakeIngest();
        Str::createUuidsUsingSequence([
            $jobId = 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
            $attemptId = '02cb9091-8973-427f-8d3f-042f2ec4e862',
        ]);

        if ($this->isVapor) {
            $this->setupVaporEnvironment();
            $this->bindLambdaEventForJob(new FailedJob);
        } else {
            FailedJob::dispatch();
        }

        Artisan::call($workCommand, $this->workOptions($workCommand));

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
                'connection' => $this->isVapor ? 'sqs' : 'database',
                'queue' => 'default',
                'status' => 'failed',
                'duration' => 2500,
                'exceptions' => 1,
                'logs' => 0,
                'queries' => $this->isVapor ? 1 : 5,
                'lazy_loads' => 0,
                'jobs_queued' => 0,
                'mail' => 0,
                'notifications' => 0,
                'outgoing_requests' => 0,
                'files_read' => 0,
                'files_written' => 0,
                'cache_events' => $this->isVapor ? 0 : 1,
                'hydrated_models' => 0,
                'peak_memory_usage' => 1234,
                'exception_preview' => 'Job failed',
            ],
        ]);
    }

    public function test_it_does_not_ingest_jobs_dispatched_on_the_sync_queue(): void
    {
        $ingest = $this->fakeIngest();
        ProcessedJob::dispatchSync();

        $ingest->assertWrittenTimes(0);
    }

    #[DataProvider('workCommands')]
    public function test_it_captures_closure_job($workCommand): void
    {
        $this->isVapor = $workCommand === 'vapor:work';

        $ingest = $this->fakeIngest();
        Str::createUuidsUsingSequence([
            $jobId = 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
            $attemptId = '02cb9091-8973-427f-8d3f-042f2ec4e862',
        ]);
        $line = __LINE__ + 1;
        $closure = function (): void {
            Date::setTestNow(now()->addMicroseconds(2500));
        };

        if ($this->isVapor) {
            $this->setupVaporEnvironment();
            $this->bindLambdaEventForClosure($closure, $line);
        } else {
            dispatch($closure);
        }

        Artisan::call($workCommand, $this->workOptions($workCommand));

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
                'connection' => $this->isVapor ? 'sqs' : 'database',
                'queue' => 'default',
                'status' => 'processed',
                'duration' => 2500,
                'exceptions' => 0,
                'logs' => 0,
                'queries' => $this->isVapor ? 0 : 4,
                'lazy_loads' => 0,
                'jobs_queued' => 0,
                'mail' => 0,
                'notifications' => 0,
                'outgoing_requests' => 0,
                'files_read' => 0,
                'files_written' => 0,
                'cache_events' => $this->isVapor ? 0 : 1,
                'hydrated_models' => 0,
                'peak_memory_usage' => 1234,
                'exception_preview' => '',
            ],
        ]);
    }

    #[DataProvider('workCommands')]
    public function test_it_captures_queued_event_listener($workCommand): void
    {
        $this->isVapor = $workCommand === 'vapor:work';

        $ingest = $this->fakeIngest();
        Str::createUuidsUsingSequence([
            $jobId = 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
            $attemptId = '02cb9091-8973-427f-8d3f-042f2ec4e862',
        ]);
        Event::listen(MyJobAttemptEvent::class, MyEventListener::class);

        if ($this->isVapor) {
            $this->setupVaporEnvironment();
            $this->bindLambdaEventForJob(new MyEventListener);
        } else {
            Event::dispatch(new MyJobAttemptEvent);
        }

        Artisan::call($workCommand, $this->workOptions($workCommand));

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
                'connection' => $this->isVapor ? 'sqs' : 'database',
                'queue' => 'default',
                'status' => 'processed',
                'duration' => 2500,
                'exceptions' => 0,
                'logs' => 0,
                'queries' => $this->isVapor ? 0 : 4,
                'lazy_loads' => 0,
                'jobs_queued' => 0,
                'mail' => 0,
                'notifications' => 0,
                'outgoing_requests' => 0,
                'files_read' => 0,
                'files_written' => 0,
                'cache_events' => $this->isVapor ? 0 : 1,
                'hydrated_models' => 0,
                'peak_memory_usage' => 1234,
                'exception_preview' => '',
            ],
        ]);
    }

    #[DataProvider('workCommands')]
    public function test_it_captures_queued_mail($workCommand): void
    {
        $this->isVapor = $workCommand === 'vapor:work';

        $ingest = $this->fakeIngest();
        Str::createUuidsUsingSequence([
            $jobId = 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
            $attemptId = '02cb9091-8973-427f-8d3f-042f2ec4e862',
        ]);

        if ($this->isVapor) {
            $this->setupVaporEnvironment();
            $this->bindLambdaEventForJob(new SendQueuedMailable((new JobAttemptMail)->to('tim@laravel.com')));
        } else {
            Mail::to('tim@laravel.com')->queue(new JobAttemptMail);
        }

        Artisan::call($workCommand, $this->workOptions($workCommand));

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
                'connection' => $this->isVapor ? 'sqs' : 'database',
                'queue' => 'default',
                'status' => 'processed',
                'duration' => 2500,
                'exceptions' => 0,
                'logs' => 0,
                'queries' => $this->isVapor ? 0 : 4,
                'lazy_loads' => 0,
                'jobs_queued' => 0,
                'mail' => 1,
                'notifications' => 0,
                'outgoing_requests' => 0,
                'files_read' => 0,
                'files_written' => 0,
                'cache_events' => $this->isVapor ? 0 : 1,
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
    public function test_it_captures_multiple_job_attempts($workCommand): void
    {
        $this->isVapor = $workCommand === 'vapor:work';
        $ingest = $this->fakeIngest();

        if ($this->isVapor) {
            $this->setupVaporEnvironment();

            $this->bindLambdaEventForJob(new FailedJob, attempts: 0);
            Artisan::call($workCommand, $this->workOptions($workCommand, ['--tries' => 2]));

            $this->bindLambdaEventForJob(new FailedJob, attempts: 1);
            Artisan::call($workCommand, $this->workOptions($workCommand, ['--tries' => 2]));
        } else {
            FailedJob::dispatch();
            Artisan::call($workCommand, $this->workOptions($workCommand, ['--max-jobs' => 2, '--tries' => 2]));
        }

        $ingest->assertWrittenTimes(2);
        $ingest->assertWrite(0, 'job-attempt:0.attempt', 1);
        $ingest->assertWrite(0, 'exception:0.message', 'Job failed');
        $ingest->assertWrite(1, 'job-attempt:0.attempt', 2);
        $ingest->assertWrite(1, 'exception:0.message', 'Job failed');
    }

    #[DataProvider('workCommands')]
    public function test_it_captures_manually_reported_exceptions($workCommand): void
    {
        $this->isVapor = $workCommand === 'vapor:work';

        $ingest = $this->fakeIngest();
        Str::createUuidsUsingSequence([
            $jobId = 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
            $attemptId = '02cb9091-8973-427f-8d3f-042f2ec4e862',
        ]);
        $line = __LINE__ + 1;
        $closure = function (): void {
            Date::setTestNow(now()->addMicroseconds(2500));

            report('Whoops!');
        };

        if ($this->isVapor) {
            $this->setupVaporEnvironment();
            $this->bindLambdaEventForClosure($closure, $line);
        } else {
            dispatch($closure);
        }

        Artisan::call($workCommand, $this->workOptions($workCommand));

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
                'connection' => $this->isVapor ? 'sqs' : 'database',
                'queue' => 'default',
                'status' => 'processed',
                'duration' => 2500,
                'exceptions' => 1,
                'logs' => 0,
                'queries' => $this->isVapor ? 0 : 4,
                'lazy_loads' => 0,
                'jobs_queued' => 0,
                'mail' => 0,
                'notifications' => 0,
                'outgoing_requests' => 0,
                'files_read' => 0,
                'files_written' => 0,
                'cache_events' => $this->isVapor ? 0 : 1,
                'hydrated_models' => 0,
                'peak_memory_usage' => 1234,
                'exception_preview' => '',
            ],
        ]);
        $ingest->assertLatestWrite('exception:0', function ($exception) use ($line) {
            $this->assertArrayIsIdenticalToArrayOnlyConsideringListOfKeys($expected = [
                'trace_id' => '0d3ca349-e222-4982-ac23-2343692de258',
                'execution_source' => 'job',
                'execution_id' => '02cb9091-8973-427f-8d3f-042f2ec4e862',
                'execution_preview' => "Closure (JobAttemptSensorTest.php:{$line})",
                'execution_stage' => 'action',
                'message' => 'Whoops!',
                'handled' => true,
            ], $exception, array_keys($expected));

            return true;
        });
    }

    #[DataProvider('workCommands')]
    public function test_it_resets_the_state_between_job_attempts($workCommand): void
    {
        $this->isVapor = $workCommand === 'vapor:work';

        $ingest = $this->fakeIngest();

        if ($this->isVapor) {
            $this->setupVaporEnvironment();

            $this->bindLambdaEventForJob(new FailedJob);
            Artisan::call($workCommand, $this->workOptions($workCommand));

            $this->bindLambdaEventForJob(new ProcessedJob);
            Artisan::call($workCommand, $this->workOptions($workCommand));
        } else {
            FailedJob::dispatch();
            ProcessedJob::dispatch();
            Artisan::call($workCommand, $this->workOptions($workCommand, ['--max-jobs' => 2]));
        }

        $ingest->assertWrittenTimes(2);
        $ingest->assertWrite(0, 'job-attempt:0.exception_preview', 'Job failed');
        $ingest->assertWrite(1, 'job-attempt:0.exception_preview', '');
    }

    #[DataProvider('workCommands')]
    public function test_it_does_not_ingest_or_build_up_state_while_idle($workCommand): void
    {
        if ($workCommand === 'vapor:work') {
            // Vapor doesn't loop for jobs; it processes one job and exits
            $this->markTestSkipped('vapor:work does not loop waiting for jobs.');

            return;
        }

        $ingest = $this->fakeIngest();
        $loops = 0;
        Queue::looping(function () use (&$loops): void {
            $loops++;
        });

        Artisan::call($workCommand, ['--max-time' => 0.05, '--sleep' => 0]);

        $this->assertGreaterThan(50, $loops);
        $ingest->assertWrittenTimes(0);
        $this->assertCount(2, $this->core->ingest->buffer);  // popping query + illuminate:queue:restart
    }

    #[DataProvider('workCommands')]
    public function test_it_captures_all_queue_events_for_a_job($workCommand): void
    {
        $this->isVapor = $workCommand === 'vapor:work';

        $ingest = $this->fakeIngest();
        Str::createUuidsUsingSequence([
            $jobId = 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
            $attemptId = '02cb9091-8973-427f-8d3f-042f2ec4e862',
        ]);
        $this->prependListener(QueryExecuted::class, function (QueryExecuted $event): void {
            $event->time = 1;

            $this->travelTo(now()->addMicroseconds(1000));
        });

        if ($this->isVapor) {
            $this->setupVaporEnvironment();
            $this->bindLambdaEventForJob(new ProcessedJob);
        } else {
            ProcessedJob::dispatch();
        }

        Artisan::call($workCommand, $this->workOptions($workCommand));

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($write) {
            if ($this->isVapor) {
                // For Vapor, we only get the job-attempt record since there are no database queries
                $this->assertCount(1, $write);
                $this->assertArrayIsIdenticalToArrayOnlyConsideringListOfKeys($expected = [
                    't' => 'job-attempt',
                    'trace_id' => '0d3ca349-e222-4982-ac23-2343692de258',
                    'name' => 'Tests\Feature\Sensors\ProcessedJob',
                ], $write[0], array_keys($expected));
            } else {
                $this->assertCount(6, $write);
                $this->assertArrayIsIdenticalToArrayOnlyConsideringListOfKeys($expected = [
                    't' => 'query',
                    'trace_id' => '0d3ca349-e222-4982-ac23-2343692de258',
                    'execution_source' => 'job',
                    'execution_id' => '02cb9091-8973-427f-8d3f-042f2ec4e862',
                    'execution_preview' => 'Tests\Feature\Sensors\ProcessedJob',
                    'execution_stage' => 'action',
                    'sql' => 'select * from "jobs" where "queue" = ? and (("reserved_at" is null and "available_at" <= ?) or ("reserved_at" <= ?)) order by "id" asc limit 1',
                ], $write[0], array_keys($expected));
                $this->assertArrayIsIdenticalToArrayOnlyConsideringListOfKeys($expected = [
                    't' => 'query',
                    'trace_id' => '0d3ca349-e222-4982-ac23-2343692de258',
                    'execution_source' => 'job',
                    'execution_id' => '02cb9091-8973-427f-8d3f-042f2ec4e862',
                    'execution_preview' => 'Tests\Feature\Sensors\ProcessedJob',
                    'execution_stage' => 'action',
                    'sql' => 'update "jobs" set "reserved_at" = ?, "attempts" = ? where "id" = ?',
                ], $write[1], array_keys($expected));
                $this->assertArrayIsIdenticalToArrayOnlyConsideringListOfKeys($expected = [
                    't' => 'query',
                    'trace_id' => '0d3ca349-e222-4982-ac23-2343692de258',
                    'execution_source' => 'job',
                    'execution_id' => '02cb9091-8973-427f-8d3f-042f2ec4e862',
                    'execution_preview' => 'Tests\Feature\Sensors\ProcessedJob',
                    'execution_stage' => 'action',
                    'sql' => 'select * from "jobs" where "id" = ? limit 1',
                ], $write[2], array_keys($expected));
                $this->assertArrayIsIdenticalToArrayOnlyConsideringListOfKeys($expected = [
                    't' => 'query',
                    'trace_id' => '0d3ca349-e222-4982-ac23-2343692de258',
                    'execution_source' => 'job',
                    'execution_id' => '02cb9091-8973-427f-8d3f-042f2ec4e862',
                    'execution_preview' => 'Tests\Feature\Sensors\ProcessedJob',
                    'execution_stage' => 'action',
                    'sql' => 'delete from "jobs" where "id" = ?',
                ], $write[3], array_keys($expected));
                $this->assertArrayIsIdenticalToArrayOnlyConsideringListOfKeys($expected = [
                    't' => 'job-attempt',
                    'trace_id' => '0d3ca349-e222-4982-ac23-2343692de258',
                    'name' => 'Tests\Feature\Sensors\ProcessedJob',
                ], $write[4], array_keys($expected));
                $this->assertArrayIsIdenticalToArrayOnlyConsideringListOfKeys($expected = [
                    't' => 'cache-event',
                    'trace_id' => '0d3ca349-e222-4982-ac23-2343692de258',
                    'execution_source' => 'job',
                    'execution_id' => '02cb9091-8973-427f-8d3f-042f2ec4e862',
                    'execution_preview' => 'Tests\Feature\Sensors\ProcessedJob',
                    'execution_stage' => 'action',
                    'trace_id' => '0d3ca349-e222-4982-ac23-2343692de258',
                    'key' => 'illuminate:queue:restart',
                ], $write[5], array_keys($expected));
            }

            return true;
        });
    }

    #[DataProvider('workCommands')]
    public function test_it_captures_counts_occuring_outside_job_execution($workCommand): void
    {
        $this->isVapor = $workCommand === 'vapor:work';

        $ingest = $this->fakeIngest();
        Str::createUuidsUsingSequence([
            $jobId = 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
            $attemptId = '02cb9091-8973-427f-8d3f-042f2ec4e862',
        ]);
        Http::fake(['https://laravel.com' => Http::response()]);
        Event::listen(function (CacheMissed $event): void {
            if ($event->key !== 'illuminate:queue:restart') {
                return;
            }

            Http::get('https://laravel.com');
        });

        if ($this->isVapor) {
            $this->setupVaporEnvironment();
            $this->bindLambdaEventForJob(new ProcessedJob);
        } else {
            ProcessedJob::dispatch();
        }

        Artisan::call($workCommand, $this->workOptions($workCommand));

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($write) {
            if ($this->isVapor) {
                $this->assertCount(1, $write);
                $this->assertArrayIsIdenticalToArrayOnlyConsideringListOfKeys($expected = [
                    't' => 'job-attempt',
                    'outgoing_requests' => 0,
                ], $write[0], array_keys($expected));
            } else {
                $this->assertCount(7, $write);
                $this->assertArrayIsIdenticalToArrayOnlyConsideringListOfKeys($expected = [
                    't' => 'job-attempt',
                    'outgoing_requests' => 1,
                ], $write[4], array_keys($expected));
                $this->assertArrayIsIdenticalToArrayOnlyConsideringListOfKeys($expected = [
                    't' => 'outgoing-request',
                ], $write[6], array_keys($expected));
            }

            return true;
        });
    }

    protected function workOptions(string $workCommand, array $overrides = []): array
    {
        if ($workCommand === 'vapor:work') {
            return [
                '--tries' => 1,
                '--timeout' => 0,
                '--delay' => 0,
                ...$overrides,
            ];
        }

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
        yield ['vapor:work'];
    }

    protected function bindLambdaEventForJob(mixed $job, int $attempts = 0): void
    {
        app()->bind(LambdaEvent::class, function () use ($job, $attempts) {
            return new LambdaEvent([
                'Records' => [
                    [
                        'messageId' => '12345678-abcd-1234-efgh-123456789',
                        'receiptHandle' => 'AQEBwJnKyrHigUMZiWwCK1RjTXJNLtjNt2AbFd12uKxQo/bUIqAfA3LIvT7v8rAB+9LzJkUiKY1YPwULB6FX7Y8Bq3rBPqNhZm8xJKL5g8VwA/5X2r9EzUHgGjTLNmV8xJKL5g8VwA/5X2r9EzUHgGjTLNmV8xJKL5g8VwA/5X2r9EzUHgGjTLNmV8xJKL5g8VwA/5X2r9EzUHgGjTLBCDEFGH',
                        'body' => json_encode([
                            'uuid' => (string) Str::uuid(),
                            'displayName' => $job instanceof SendQueuedMailable ? $job->mailable::class : $job::class,
                            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
                            'maxTries' => null,
                            'maxExceptions' => null,
                            'failOnTimeout' => false,
                            'backoff' => null,
                            'timeout' => null,
                            'retryUntil' => null,
                            'data' => [
                                'commandName' => $job::class,
                                'command' => serialize($job),
                            ],
                            'attempts' => $attempts,
                            'delay' => null,
                        ]),
                        'attributes' => [
                            'ApproximateReceiveCount' => (string) ($attempts + 1),
                            'SentTimestamp' => 1751529944619,
                            'SenderId' => 'AIDACKCEVSQ6C2EXAMPLE',
                            'ApproximateFirstReceiveTimestamp' => 1751529944619,
                        ],
                        'messageAttributes' => [],
                        'md5OfBody' => 'd41d8cd98f00b204e9800998ecf8427e',
                        'eventSource' => 'aws:sqs',
                        'eventSourceARN' => 'arn:aws:sqs:us-east-1:123456789:default',
                        'awsRegion' => 'us-east-1',
                    ],
                ],
            ]);
        });
    }

    protected function bindLambdaEventForClosure(callable $closure, int $line): void
    {
        app()->bind(LambdaEvent::class, function () use ($closure, $line) {
            return new LambdaEvent([
                'Records' => [
                    [
                        'messageId' => '12345678-abcd-1234-efgh-123456789',
                        'receiptHandle' => 'AQEBwJnKyrHigUMZiWwCK1RjTXJNLtjNt2AbFd12uKxQo/bUIqAfA3LIvT7v8rAB+9LzJkUiKY1YPwULB6FX7Y8Bq3rBPqNhZm8xJKL5g8VwA/5X2r9EzUHgGjTLNmV8xJKL5g8VwA/5X2r9EzUHgGjTLNmV8xJKL5g8VwA/5X2r9EzUHgGjTLNmV8xJKL5g8VwA/5X2r9EzUHgGjTLBCDEFGH',
                        'body' => json_encode([
                            'uuid' => (string) Str::uuid(),
                            'displayName' => "Closure (JobAttemptSensorTest.php:{$line})",
                            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
                            'maxTries' => null,
                            'maxExceptions' => null,
                            'failOnTimeout' => false,
                            'backoff' => null,
                            'timeout' => null,
                            'retryUntil' => null,
                            'data' => [
                                'commandName' => 'Illuminate\\Queue\\CallQueuedClosure',
                                'command' => serialize(CallQueuedClosure::create($closure)),
                            ],
                            'attempts' => 0,
                            'delay' => null,
                        ]),
                        'attributes' => [
                            'ApproximateReceiveCount' => '1',
                            'SentTimestamp' => 1751529944619,
                            'SenderId' => 'AIDACKCEVSQ6C2EXAMPLE',
                            'ApproximateFirstReceiveTimestamp' => 1751529944619,
                        ],
                        'messageAttributes' => [],
                        'md5OfBody' => 'd41d8cd98f00b204e9800998ecf8427e',
                        'eventSource' => 'aws:sqs',
                        'eventSourceARN' => 'arn:aws:sqs:us-east-1:123456789:default',
                        'awsRegion' => 'us-east-1',
                    ],
                ],
            ]);
        });
    }

    public function test_queue_workers_that_remove_successful_jobs_and_make_network_call_to_determine_attempts_like_beanstalkd_can_capture_attempts(): void
    {
        $ingest = $this->fakeIngest();
        Queue::addConnector('database', function () {
            return new class($this->app['db']) extends DatabaseConnector
            {
                public function connect(array $config)
                {
                    return new class($this->connections->connection($config['connection'] ?? null), $config['table'], $config['queue'], $config['retry_after'] ?? 60, $config['after_commit'] ?? null) extends DatabaseQueue
                    {
                        protected function marshalJob($queue, $job)
                        {
                            return new class($this->container, $this, $this->markJobAsReserved($job), $this->connectionName, $queue) extends DatabaseJob
                            {
                                public function attempts()
                                {
                                    if ($this->instance?->handled) {
                                        throw new RuntimeException('Job has been deleted');
                                    }

                                    return 1;
                                }
                            };
                        }
                    };
                }
            };
        });

        JobThatMarksItselfAsHandled::dispatch();

        Artisan::call('queue:work', $this->workOptions('queue:work'));

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('exception:*', []);
        $ingest->assertLatestWrite('job-attempt:0.attempt', 1);
    }
}

final class ProcessedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Date::setTestNow(now()->addMicroseconds(2500));
    }
}

final class ReleasedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Date::setTestNow(now()->addMicroseconds(2500));

        $this->release();
    }
}

final class FailedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Date::setTestNow(now()->addMicroseconds(2500));

        throw new RuntimeException('Job failed');
    }
}

final class ExceptionReportingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Date::setTestNow(now()->addMicroseconds(2500));

        report(new RuntimeException('Whoops!'));
    }
}

final class MyEventListener implements ShouldQueue
{
    public function handle(): void
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

class JobThatMarksItselfAsHandled implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $handled = false;

    public function handle(): void
    {
        $this->handled = true;
    }
}
