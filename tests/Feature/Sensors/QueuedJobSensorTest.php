<?php

namespace Tests\Feature\Sensors;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\JobQueueing;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Compatibility;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

use function hash;
use function now;

class QueuedJobSensorTest extends TestCase
{
    protected function setUp(): void
    {
        $this->forceRequestExecutionState();

        parent::setUp();

        $this->setDeploy('v1.2.3');
        $this->setServerName('web-01');
        $this->setPeakMemory(1234);
        $this->setTraceId('00000000-0000-0000-0000-000000000000');
        $this->setExecutionId('00000000-0000-0000-0000-000000000001');
        $this->setExecutionStart(CarbonImmutable::parse('2000-01-01 01:02:03.456789'));
    }

    public function test_it_can_ingest_queued_jobs()
    {
        $ingest = $this->fakeIngest();
        $this->prependListener(QueryExecuted::class, function (QueryExecuted $event) {
            if (! RefreshDatabaseState::$migrated) {
                return false;
            }

            $event->time = 5.2;

            $this->travelTo(now()->addMicroseconds(5200));
        });
        Route::post('/users', function () {
            Str::createUuidsUsingSequence(['00000000-0000-0000-0000-000000000000']);
            MyJob::dispatch();
        });

        $response = $this->post('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.jobs_queued', 1);
        $ingest->assertLatestWrite('queued-job:*', [
            [
                'v' => 1,
                't' => 'queued-job',
                'timestamp' => 946688523.461989,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => hash('xxh128', 'Tests\Feature\Sensors\MyJob'),
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'execution_source' => 'request',
                'execution_id' => '00000000-0000-0000-0000-000000000001',
                'execution_preview' => 'POST /users',
                'execution_stage' => 'action',
                'user' => '',
                'job_id' => '00000000-0000-0000-0000-000000000000',
                'name' => 'Tests\Feature\Sensors\MyJob',
                'connection' => 'database',
                'queue' => Compatibility::$queueNameCapturable ? 'default' : '',
                'duration' => 5200,
            ],
        ]);
    }

    public function test_it_falls_back_to_the_connections_default_queue()
    {
        $this->markTestSkippedWhen(! Compatibility::$queueNameCapturable, 'Requires a more recent framework version');

        $ingest = $this->fakeIngest();
        Config::set('queue.connections.database.queue', 'connection-default');
        Route::post('/users', function () {
            MyJob::dispatch();
        });

        $response = $this->post('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('queued-job:0.queue', 'connection-default');
    }

    public function test_it_does_not_ingest_jobs_dispatched_on_the_sync_queue()
    {
        $ingest = $this->fakeIngest();
        $this->withoutExceptionHandling();
        Route::post('/users', function () {
            MyJob::dispatchSync();
            MyJob::dispatch()->onConnection('sync');
            Bus::dispatchNow(new MyJob);
            Bus::dispatchSync(new MyJob);
            Bus::dispatch((new MyJob)->onConnection('sync'));
        });

        $response = $this->post('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('queued-job:*', []);
    }

    public function test_it_captures_queued_event_queue_name()
    {
        $this->markTestSkippedWhen(! Compatibility::$queueNameCapturable, 'Requires a more recent framework version');

        $ingest = $this->fakeIngest();
        $this->prependListener(QueryExecuted::class, function (QueryExecuted $event) {
            if (! RefreshDatabaseState::$migrated) {
                return false;
            }
        });

        Route::post('/users', function () {
            Event::listen('my-event', MyListenerWithCustomQueue::class);
            Event::listen(MyQueuedJobEvent::class, MyListenerWithCustomQueue::class);
            Event::listen(MyQueuedJobEvent::class, MyListenerWithViaQueue::class);
            Event::dispatch('my-event');
            Event::dispatch(new MyQueuedJobEvent);
        });

        $response = $this->post('/users');

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('queued-job:0.queue', 'custom_queue');
        $ingest->assertLatestWrite('queued-job:1.queue', 'custom_queue');
        $ingest->assertLatestWrite('queued-job:2.queue', 'custom_queue');
    }

    public function test_it_captures_queued_mail()
    {
        $ingest = $this->fakeIngest();
        $this->prependListener(QueryExecuted::class, function (QueryExecuted $event) {
            if (! RefreshDatabaseState::$migrated) {
                return false;
            }
        });

        Route::post('/users', function () {
            Str::createUuidsUsingSequence([
                Uuid::fromString('00000000-0000-0000-0000-000000000002'),
            ]);
            Mail::to('tim@laravel.com')->queue(new MyQueuedMail);
        });

        $response = $this->post('/users');

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('queued-job:0', [
            'v' => 1,
            't' => 'queued-job',
            'timestamp' => 946688523.456789,
            'deploy' => 'v1.2.3',
            'server' => 'web-01',
            '_group' => hash('xxh128', 'Tests\Feature\Sensors\MyQueuedMail'),
            'trace_id' => '00000000-0000-0000-0000-000000000000',
            'execution_source' => 'request',
            'execution_id' => '00000000-0000-0000-0000-000000000001',
            'execution_preview' => 'POST /users',
            'execution_stage' => 'action',
            'user' => '',
            'job_id' => '00000000-0000-0000-0000-000000000002',
            'name' => 'Tests\Feature\Sensors\MyQueuedMail',
            'connection' => 'database',
            'queue' => Compatibility::$queueNameCapturable ? 'default' : '',
            'duration' => 0,
        ]);
    }

    public function test_it_normalizes_sqs_queue_names()
    {
        $this->markTestSkippedWhen(! Compatibility::$queueNameCapturable, 'Requires a more recent framework version');

        $ingest = $this->fakeIngest();
        Config::set('queue.connections.my-sqs-queue', [
            'driver' => 'sqs',
            'prefix' => 'https://sqs.us-east-1.amazonaws.com/your-account-id',
            'queue' => 'queue-name',
            'suffix' => '-production',
        ]);

        $this->core->sensor->queuedJob(new JobQueueing(
            connectionName: 'my-sqs-queue',
            queue: 'https://sqs.us-east-1.amazonaws.com/your-account-id/queue-name-production',
            job: 'Tests\Feature\Sensors\MyJobClass',
            payload: '{"uuid":"00000000-0000-0000-0000-000000000000"}',
            delay: 0,
        ));

        $this->core->sensor->queuedJob(new JobQueued(
            connectionName: 'my-sqs-queue',
            queue: 'https://sqs.us-east-1.amazonaws.com/your-account-id/queue-name-production',
            id: Str::uuid()->toString(),
            job: 'Tests\Feature\Sensors\MyJobClass',
            payload: '{"uuid":"00000000-0000-0000-0000-000000000000"}',
            delay: 0,
        ));
        $ingest->digest();

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('queued-job:0.queue', 'queue-name');
    }

    public function test_it_handles_missing_queue_value()
    {
        $this->markTestSkippedWhen(! Compatibility::$queueNameCapturable, 'Requires a more recent framework version');
        $ingest = $this->fakeIngest();
        $this->prependListener(QueryExecuted::class, function (QueryExecuted $event) {
            if (! RefreshDatabaseState::$migrated) {
                return false;
            }
        });
        Route::post('/users', function () {
            MyJob::dispatch();
            MyJob::dispatch()->onQueue('foobar');
        });

        $response = $this->post('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('queued-job:0.queue', 'default');
        $ingest->assertLatestWrite('queued-job:1.queue', 'foobar');
    }
}

final class MyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        //
    }
}

final class MyListenerWithCustomQueue implements ShouldQueue
{
    use InteractsWithQueue;

    public $queue = 'custom_queue';

    public function handle(): void
    {
        //
    }
}

final class MyListenerWithViaQueue implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(): void
    {
        //
    }

    public function viaQueue(object $event)
    {
        return 'custom_queue';
    }
}

class MyQueuedJobEvent
{
    use Dispatchable;
}

class MyQueuedMail extends Mailable
{
    public function content(): Content
    {
        Date::setTestNow(now()->addMicroseconds(2500));

        return new Content(
            view: 'mail',
        );
    }
}
