<?php

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\WithConsoleEvents;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Laravel\Nightwatch\Types\Str;

use function Orchestra\Testbench\Pest\defineEnvironment;
use function Pest\Laravel\travelTo;

uses(WithConsoleEvents::class);

defineEnvironment(function () {
    forceCommandExecutionState();
    Str::createUuidsUsing(fn () => '00000000-0000-0000-0000-000000000000');
    $this->ingest = fakeIngest();
});

beforeEach(function () {
    setDeploy('v1.2.3');
    setServerName('scheduler-01');
    setPeakMemory(1234);
    setExecutionStart(CarbonImmutable::parse('2000-01-01 01:02:03.456789'));
    ignoreMigrationQueries();
});

it('ingests processed tasks', function () {
    $line = __LINE__ + 1;
    $task = Schedule::call(fn () => travelTo(now()->addMicroseconds(1_000_000)))->everyMinute();

    Artisan::call('schedule:run');

    $this->ingest->assertWrittenTimes(1);
    $this->ingest->assertLatestWrite('scheduled-task:*', [
        [
            'v' => 1,
            't' => 'scheduled-task',
            'timestamp' => 946688523.456789,
            'deploy' => 'v1.2.3',
            'server' => 'scheduler-01',
            '_group' => hash('md5', "{$task->command},{$task->expression},{$task->timezone}"),
            'trace_id' => '00000000-0000-0000-0000-000000000000',
            'name' => "Closure at: tests/Feature/Sensors/ScheduledTaskSensorTest.php:{$line}",
            'cron' => '* * * * *',
            'timezone' => 'UTC',
            'without_overlapping' => false,
            'on_one_server' => false,
            'run_in_background' => false,
            'even_in_maintenance_mode' => false,
            'status' => 'processed',
            'duration' => 1_000_000,
            'exceptions' => 0,
            'logs' => 0,
            'queries' => 0,
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

it('ingests skipped tasks', function () {
    $line = __LINE__ + 1;
    $task = Schedule::call(fn () => travelTo(now()->addMicroseconds(1_000_000)))
        ->skip(fn () => true)
        ->everyMinute();

    Artisan::call('schedule:run');

    $this->ingest->assertWrittenTimes(1);
    $this->ingest->assertLatestWrite('scheduled-task:*', [
        [
            'v' => 1,
            't' => 'scheduled-task',
            'timestamp' => 946688523.456789,
            'deploy' => 'v1.2.3',
            'server' => 'scheduler-01',
            '_group' => hash('md5', "{$task->command},{$task->expression},{$task->timezone}"),
            'trace_id' => '00000000-0000-0000-0000-000000000000',
            'name' => "Closure at: tests/Feature/Sensors/ScheduledTaskSensorTest.php:{$line}",
            'cron' => '* * * * *',
            'timezone' => 'UTC',
            'without_overlapping' => false,
            'on_one_server' => false,
            'run_in_background' => false,
            'even_in_maintenance_mode' => false,
            'status' => 'skipped',
            'duration' => 0,
            'exceptions' => 0,
            'logs' => 0,
            'queries' => 0,
            'lazy_loads' => 0,
            'jobs_queued' => 0,
            'mail' => 0,
            'notifications' => 0,
            'outgoing_requests' => 0,
            'files_read' => 0,
            'files_written' => 0,
            'cache_events' => 1, // TODO: `ScheduledTaskStarting` event is not fired for skipped tasks and the counts are not reset.
            'hydrated_models' => 0,
            'peak_memory_usage' => 1234,
        ],
    ]);
});

it('ingests failed tasks', function () {
    $line = __LINE__ + 1;
    $task = Schedule::call(fn () => travelTo(now()->addMicroseconds(1_000_000)) & throw new Exception())
        ->everyMinute();

    Artisan::call('schedule:run');

    $this->ingest->assertWrittenTimes(1);
    $this->ingest->assertLatestWrite('scheduled-task:*', [
        [
            'v' => 1,
            't' => 'scheduled-task',
            'timestamp' => 946688523.456789,
            'deploy' => 'v1.2.3',
            'server' => 'scheduler-01',
            '_group' => hash('md5', "{$task->command},{$task->expression},{$task->timezone}"),
            'trace_id' => '00000000-0000-0000-0000-000000000000',
            'name' => "Closure at: tests/Feature/Sensors/ScheduledTaskSensorTest.php:{$line}",
            'cron' => '* * * * *',
            'timezone' => 'UTC',
            'without_overlapping' => false,
            'on_one_server' => false,
            'run_in_background' => false,
            'even_in_maintenance_mode' => false,
            'status' => 'failed',
            'duration' => 1_000_000,
            'exceptions' => 1,
            'logs' => 0,
            'queries' => 0,
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

it('resets trace ID and timestamp before each task run', function () {
    //
})->todo();

it('normalizes task name for named closure', function () {
    $task = Schedule::call(fn () => travelTo(now()->addMicroseconds(1_000_000)))
        ->name('named-closure')
        ->everyMinute();

    Artisan::call('schedule:run');

    $this->ingest->assertWrittenTimes(1);
    $this->ingest->assertLatestWrite('scheduled-task:*', [
        [
            'v' => 1,
            't' => 'scheduled-task',
            'timestamp' => 946688523.456789,
            'deploy' => 'v1.2.3',
            'server' => 'scheduler-01',
            '_group' => hash('md5', "{$task->command},{$task->expression},{$task->timezone}"),
            'trace_id' => '00000000-0000-0000-0000-000000000000',
            'name' => 'named-closure',
            'cron' => '* * * * *',
            'timezone' => 'UTC',
            'without_overlapping' => false,
            'on_one_server' => false,
            'run_in_background' => false,
            'even_in_maintenance_mode' => false,
            'status' => 'processed',
            'duration' => 1_000_000,
            'exceptions' => 0,
            'logs' => 0,
            'queries' => 0,
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

it('normalizes task name for invokable class', function () {
    class ProcessFlights
    {
        public function __invoke()
        {
            travelTo(now()->addMicroseconds(1_000_000));
        }
    }

    $task = Schedule::call(new ProcessFlights)->everyMinute();

    Artisan::call('schedule:run');

    $this->ingest->assertWrittenTimes(1);
    $this->ingest->assertLatestWrite('scheduled-task:*', [
        [
            'v' => 1,
            't' => 'scheduled-task',
            'timestamp' => 946688523.456789,
            'deploy' => 'v1.2.3',
            'server' => 'scheduler-01',
            '_group' => hash('md5', "{$task->command},{$task->expression},{$task->timezone}"),
            'trace_id' => '00000000-0000-0000-0000-000000000000',
            'name' => 'ProcessFlights',
            'cron' => '* * * * *',
            'timezone' => 'UTC',
            'without_overlapping' => false,
            'on_one_server' => false,
            'run_in_background' => false,
            'even_in_maintenance_mode' => false,
            'status' => 'processed',
            'duration' => 1_000_000,
            'exceptions' => 0,
            'logs' => 0,
            'queries' => 0,
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

// it('normalizes task names', function (Event $task) {
//     //
// })
//     ->with([
//         'artisan command signature' => fn () => Schedule::command('inspire')->everyMinute(),
//         'artisan command class' => fn () => Schedule::command()->everyMinute(),
//         'job' => fn () => Schedule::job(new class() {})->everyMinute(),
//         'job with handle method' => fn () => Schedule::call([])->everyMinute(),
//         'shell command' => fn () => Schedule::exec('echo "Hello, world!"')->everyMinute(),
//     ])
//     ->todo();
