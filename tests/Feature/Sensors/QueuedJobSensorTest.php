<?php

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

use function Pest\Laravel\post;
use function Pest\Laravel\travelTo;
use function Pest\Laravel\withoutExceptionHandling;

beforeEach(function () {
    setDeployId('v1.2.3');
    setServerName('web-01');
    setPeakMemoryInKilobytes(1234);
    setTraceId('00000000-0000-0000-0000-000000000000');
    syncClock(CarbonImmutable::parse('2000-01-01 00:00:00'));
});

it('can ingest cache misses', function () {
    $ingest = fakeIngest();
    prependListener(QueryExecuted::class, function (QueryExecuted $event) {
        if (! RefreshDatabaseState::$migrated) {
            return false;
        }

        $event->time = 5.2;

        travelTo(now()->addMilliseconds(5.2));
    });
    Route::post('/users', function () {
        Route::post('/users', function () {
            travelTo(now()->addMilliseconds(2.5));
            Str::createUuidsUsingSequence(['00000000-0000-0000-0000-000000000000']);
            MyJob::dispatch();
            Str::createUuidsNormally();
        });
        travelTo(now()->addMilliseconds(2.5));
        Str::createUuidsUsingSequence(['00000000-0000-0000-0000-000000000000']);
        MyJob::dispatch();
        Str::createUuidsNormally();
    });

    $response = post('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('requests', [
        [
            'v' => 1,
            'timestamp' => 946684800,
            'deploy_id' => 'v1.2.3',
            'server' => 'web-01',
            'group' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
            'trace_id' => '00000000-0000-0000-0000-000000000000',
            'user' => '',
            'method' => 'POST',
            // 'route' => '/users',
            'scheme' => 'http',
            'url_user' => '',
            'host' => 'localhost',
            'port' => '80',
            'path' => '/users',
            'query' => '',
            'route_name' => '',
            'route_methods' => ['POST'],
            'route_domain' => '',
            'route_path' => '/users',
            'route_action' => 'Closure',
            'ip' => '127.0.0.1',
            'duration' => 8,
            'status_code' => '200',
            'request_size_kilobytes' => 0,
            'response_size_kilobytes' => 0,
            'queries' => 1,
            'queries_duration' => 5200,
            'lazy_loads' => 0,
            'lazy_loads_duration' => 0,
            'jobs_queued' => 1,
            'mail_queued' => 0,
            'mail_sent' => 0,
            'mail_duration' => 0,
            'notifications_queued' => 0,
            'notifications_sent' => 0,
            'notifications_duration' => 0,
            'outgoing_requests' => 0,
            'outgoing_requests_duration' => 0,
            'files_read' => 0,
            'files_read_duration' => 0,
            'files_written' => 0,
            'files_written_duration' => 0,
            'cache_hits' => 0,
            'cache_misses' => 0,
            'hydrated_models' => 0,
            'peak_memory_usage_kilobytes' => 1234,
        ],
    ]);
    $ingest->assertLatestWrite('queued_jobs', [
        [
            'v' => 1,
            'timestamp' => 946684800,
            'deploy_id' => 'v1.2.3',
            'server' => 'web-01',
            'group' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
            'trace_id' => '00000000-0000-0000-0000-000000000000',
            'execution_context' => 'request',
            'execution_id' => '00000000-0000-0000-0000-000000000000',
            'execution_offset' => 7700,
            'user' => '',
            'job_id' => '00000000-0000-0000-0000-000000000000',
            'name' => 'MyJob',
            'connection' => 'database',
            'queue' => 'default',
        ],
    ]);
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
    $ingest->assertLatestWrite('queued_jobs.0.queue', 'custom_queue');
    $ingest->assertLatestWrite('queued_jobs.1.queue', 'custom_queue');
    $ingest->assertLatestWrite('queued_jobs.2.queue', 'custom_queue');
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
