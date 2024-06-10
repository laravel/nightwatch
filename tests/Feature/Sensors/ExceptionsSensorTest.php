<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;

use function Pest\Laravel\post;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    setDeployId('v1.2.3');
    setServerName('web-01');
    setPeakMemoryInKilobytes(1234);
    setTraceId('00000000-0000-0000-0000-000000000000');
    travelTo(CarbonImmutable::parse('2000-01-01 00:00:00'));
});

final class MyException extends RuntimeException
{
    public function render()
    {
        return response('', 500);
    }
}

it('ingests exceptions', function () {
    $ingest = fakeIngest();
    Config::set('app.debug', false);
    Route::post('/users', function () {
        throw new MyException('Whoops!');
    });

    $response = post('/users');

    $response->assertServerError();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite([
        'requests' => [
            [
                'timestamp' => '2000-01-01 00:00:00',
                'deploy_id' => 'v1.2.3',
                'server' => 'web-01',
                'group' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'method' => 'POST',
                'route' => '/users',
                'path' => '/users',
                'user' => '',
                'ip' => '127.0.0.1',
                'duration' => 0,
                'status_code' => '500',
                'request_size_kilobytes' => 0,
                'response_size_kilobytes' => 0,
                'peak_memory_usage_kilobytes' => 1234,
                'queries' => 0,
                'queries_duration' => 0,
                'lazy_loads' => 0,
                'lazy_loads_duration' => 0,
                'jobs_queued' => 0,
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
            ],
        ],
        'cache_events' => [],
        'commands' => [],
        'exceptions' => [
            [
                'timestamp' => '2000-01-01 00:00:00',
                'deploy_id' => 'v1.2.3',
                'server' => 'web-01',
                'group' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'execution_context' => 'request',
                'execution_id' => '00000000-0000-0000-0000-000000000000',
                'user' => '',
                'class' => 'MyException',
                'file' => 'app/Models/User.php',
                'line' => 5,
                'message' => 'Whoops!',
                'code' => 0,
                'trace' => '',
            ],
        ],
        'job_attempts' => [],
        'lazy_loads' => [],
        'logs' => [],
        'mail' => [],
        'notifications' => [],
        'outgoing_requests' => [],
        'queries' => [],
        'queued_jobs' => [],
    ]);
});

it('ingests reported exceptions')->todo();
it('can ingest arbitrary exceptions via an event')->todo();
