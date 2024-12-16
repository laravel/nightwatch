<?php

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Nightwatch\SensorManager;
use Laravel\Nightwatch\State\RequestState;
use Ramsey\Uuid\Uuid;

use function Orchestra\Testbench\Pest\defineEnvironment;
use function Pest\Laravel\post;
use function Pest\Laravel\travelTo;
use function Pest\Laravel\withoutExceptionHandling;

defineEnvironment(function () {
    forceRequestExecutionState();
});

beforeEach(function () {
    setDeploy('v1.2.3');
    setServerName('web-01');
    setPeakMemory(1234);
    setTraceId('00000000-0000-0000-0000-000000000000');
    setExecutionId('00000000-0000-0000-0000-000000000001');
    setExecutionStart(CarbonImmutable::parse('2000-01-01 01:02:03.456789'));

    Config::set('queue.default', 'database');
});

it('can ingest queued jobs', function () {
    $ingest = fakeIngest();
    prependListener(QueryExecuted::class, function (QueryExecuted $event) {
        if (! RefreshDatabaseState::$migrated) {
            return false;
        }

        $event->time = 5.2;

        travelTo(now()->addMilliseconds(5.2));
    });
    Route::post('/users', function () {
        Str::createUuidsUsingSequence(['00000000-0000-0000-0000-000000000000']);
        MyJob::dispatch();
        Str::createUuidsNormally();
    });

    $response = post('/users');

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
            '_group' => md5('MyJob'),
            'trace_id' => '00000000-0000-0000-0000-000000000000',
            'execution_source' => 'request',
            'execution_id' => '00000000-0000-0000-0000-000000000001',
            'execution_stage' => 'action',
            'user' => '',
            'job_id' => '00000000-0000-0000-0000-000000000000',
            'name' => 'MyJob',
            'connection' => 'database',
            'queue' => 'default',
            'duration' => 0,
        ],
    ]);
});

it('falls back to the connections default queue', function () {
    $ingest = fakeIngest();
    Config::set('queue.connections.database.queue', 'connection-default');
    Route::post('/users', function () {
        MyJob::dispatch();
    });

    $response = post('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('queued-job:0.queue', 'connection-default');
});

it('does not ingest jobs dispatched on the sync queue', function () {
    $ingest = fakeIngest();
    withoutExceptionHandling();
    Route::post('/users', function () {
        MyJob::dispatchSync();
        MyJob::dispatch();
        MyJob::dispatch()->onConnection('sync');
        Bus::dispatchNow(new MyJob);
        Bus::dispatchSync(new MyJob);
        Bus::dispatch((new MyJob)->onConnection('sync'));
    });

    $response = post('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
});

it('captures queued event queue name', function () {
    $ingest = fakeIngest();
    prependListener(QueryExecuted::class, function (QueryExecuted $event) {
        if (! RefreshDatabaseState::$migrated) {
            return false;
        }
    });
    Config::set('queue.default', 'database');

    Route::post('/users', function () {
        Event::listen('my-event', MyListenerWithCustomQueue::class);
        Event::listen(MyEvent::class, MyListenerWithCustomQueue::class);
        Event::listen(MyEvent::class, MyListenerWithViaQueue::class);
        Event::dispatch('my-event');
        Event::dispatch(new MyEvent);
    });

    $response = post('/users');

    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('queued-job:0.queue', 'custom_queue');
    $ingest->assertLatestWrite('queued-job:1.queue', 'custom_queue');
    $ingest->assertLatestWrite('queued-job:2.queue', 'custom_queue');
});

it('captures queued mail', function () {
    $ingest = fakeIngest();
    prependListener(QueryExecuted::class, function (QueryExecuted $event) {
        if (! RefreshDatabaseState::$migrated) {
            return false;
        }
    });
    Config::set('queue.default', 'database');

    Route::post('/users', function () {
        Str::createUuidsUsingSequence([
            Uuid::fromString('00000000-0000-0000-0000-000000000002'),
        ]);
        Mail::to('tim@laravel.com')->queue(new MyQueuedMail);
    });

    $response = post('/users');

    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('queued-job:0', [
        'v' => 1,
        't' => 'queued-job',
        'timestamp' => 946688523.456789,
        'deploy' => 'v1.2.3',
        'server' => 'web-01',
        '_group' => md5('MyQueuedMail'),
        'trace_id' => '00000000-0000-0000-0000-000000000000',
        'execution_source' => 'request',
        'execution_id' => '00000000-0000-0000-0000-000000000001',
        'execution_stage' => 'action',
        'user' => '',
        'job_id' => '00000000-0000-0000-0000-000000000002',
        'name' => 'MyQueuedMail',
        'connection' => 'database',
        'queue' => 'default',
        'duration' => 0,
    ]);
});

it('normalizes sqs queue names', function () {
    $ingest = fakeIngest();
    $sensor = app(SensorManager::class);
    $state = app(RequestState::class);
    Config::set('queue.connections.my-sqs-queue', [
        'driver' => 'sqs',
        'prefix' => 'https://sqs.us-east-1.amazonaws.com/your-account-id',
        'queue' => 'queue-name',
        'suffix' => '-production',
    ]);

    $sensor->queuedJob(new JobQueued(
        connectionName: 'my-sqs-queue',
        queue: 'https://sqs.us-east-1.amazonaws.com/your-account-id/queue-name-production',
        id: Str::uuid()->toString(),
        job: 'MyJobClass',
        payload: '{"uuid":"00000000-0000-0000-0000-000000000000"}',
        delay: 0,
    ));
    $ingest->write($state->records->flush());

    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('queued-job:0.queue', 'queue-name');
});

it('handles missing queue value', function () {
    $ingest = fakeIngest();
    prependListener(QueryExecuted::class, function (QueryExecuted $event) {
        if (! RefreshDatabaseState::$migrated) {
            return false;
        }
    });
    Config::set('queue.default', 'database');
    Route::post('/users', function () {
        MyJob::dispatch();
        MyJob::dispatch()->onQueue('foobar');
    });

    $response = post('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('queued-job:0.queue', 'default');
    $ingest->assertLatestWrite('queued-job:1.queue', 'foobar');
});

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

final class MyEvent
{
    use Dispatchable;
}

class MyQueuedMail extends Mailable implements ShouldQueue
{
    //
}
