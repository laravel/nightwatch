<?php

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Nightwatch\SensorManager;
use Laravel\Nightwatch\Sensors\CacheEventSensor;

use function Pest\Laravel\post;
use function Pest\Laravel\travelTo;
use function Pest\Laravel\withoutExceptionHandling;

beforeEach(function () {
    syncClock();
    setDeployId('v1.2.3');
    setServerName('web-01');
    setPeakMemoryInKilobytes(1234);
    setTraceId('00000000-0000-0000-0000-000000000000');
    travelTo(CarbonImmutable::parse('2000-01-01 00:00:00'));

    Event::listen(MigrationsEnded::class, fn () => App::make(SensorManager::class)->prepareForNextInvocation());
});

it('can ingest cache misses', function () {
    withoutExceptionHandling();
    $ingest = fakeIngest();
    prependListener(QueryExecuted::class, fn (QueryExecuted $event) => $event->time = 5.2);
    Route::post('/users', function () {
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
            'timestamp' => '2000-01-01 00:00:00',
            'deploy_id' => 'v1.2.3',
            'server' => 'web-01',
            'group' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
            'trace_id' => '00000000-0000-0000-0000-000000000000',
            'user' => '',
            'method' => 'POST',
            'route' => '/users',
            'path' => '/users',
            'ip' => '127.0.0.1',
            'duration' => 0,
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
            'timestamp' => '2000-01-01 00:00:00',
            'deploy_id' => 'v1.2.3',
            'server' => 'web-01',
            'group' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
            'trace_id' => '00000000-0000-0000-0000-000000000000',
            'execution_context' => 'request',
            'execution_id' => '00000000-0000-0000-0000-000000000000',
            'user' => '',
            'job_id' => '00000000-0000-0000-0000-000000000000',
            'name' => 'MyJob',
            'connection' => 'database',
            'queue' => 'default',
        ],
    ]);
});

class MyJob implements ShouldQueue
{
    use Dispatchable;
}
