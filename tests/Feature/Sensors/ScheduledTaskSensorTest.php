<?php

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\WithConsoleEvents;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Laravel\Nightwatch\Types\Str;

use function Pest\Laravel\travelTo;

uses(WithConsoleEvents::class);

beforeAll(function () {
    forceCommandExecutionState();
});

beforeEach(function () {
    $this->ingest = fakeIngest();
    setDeploy('v1.2.3');
    setServerName('scheduler-01');
    setPeakMemory(1234);
    setExecutionStart(CarbonImmutable::parse('2000-01-01 01:02:03.456789'));
    Str::createUuidsUsing(fn () => '00000000-0000-0000-0000-000000000000');
    app()->setBasePath(dirname(app()->basePath()));
});

afterEach(function () {
    Str::createUuidsNormally();
});

it('ingests processed tasks', function () {
    $line = __LINE__ + 1;
    $task = Schedule::call(fn () => travelTo(now()->addMicroseconds(1_000_000)))->everyMinute();
    $name = "Closure at: tests/Feature/Sensors/ScheduledTaskSensorTest.php:{$line}";

    Artisan::call('schedule:run');

    $this->ingest->assertWrittenTimes(1);
    $this->ingest->assertLatestWrite('scheduled-task:*', [
        [
            'v' => 1,
            't' => 'scheduled-task',
            'timestamp' => 946688523.456789,
            'deploy' => 'v1.2.3',
            'server' => 'scheduler-01',
            '_group' => hash('md5', "{$name},{$task->expression},{$task->timezone}"),
            'trace_id' => '00000000-0000-0000-0000-000000000000',
            'name' => $name,
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
    $name = "Closure at: tests/Feature/Sensors/ScheduledTaskSensorTest.php:{$line}";

    Artisan::call('schedule:run');

    $this->ingest->assertWrittenTimes(1);
    $this->ingest->assertLatestWrite('scheduled-task:*', [
        [
            'v' => 1,
            't' => 'scheduled-task',
            'timestamp' => 946688523.456789,
            'deploy' => 'v1.2.3',
            'server' => 'scheduler-01',
            '_group' => hash('md5', "{$name},{$task->expression},{$task->timezone}"),
            'trace_id' => '00000000-0000-0000-0000-000000000000',
            'name' => $name,
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
            'cache_events' => 0,
            'hydrated_models' => 0,
            'peak_memory_usage' => 0,
        ],
    ]);
});

it('ingests failed tasks', function () {
    $line = __LINE__ + 1;
    $task = Schedule::call(fn () => travelTo(now()->addMicroseconds(1_000_000)) & throw new Exception)
        ->everyMinute();
    $name = "Closure at: tests/Feature/Sensors/ScheduledTaskSensorTest.php:{$line}";

    Artisan::call('schedule:run');

    $this->ingest->assertWrittenTimes(1);
    $this->ingest->assertLatestWrite('scheduled-task:*', [
        [
            'v' => 1,
            't' => 'scheduled-task',
            'timestamp' => 946688523.456789,
            'deploy' => 'v1.2.3',
            'server' => 'scheduler-01',
            '_group' => hash('md5', "{$name},{$task->expression},{$task->timezone}"),
            'trace_id' => '00000000-0000-0000-0000-000000000000',
            'name' => $name,
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

it('resets trace ID and timestamp on each task run', function () {
    Schedule::call(fn () => travelTo(now()->addMicroseconds(1_000_000)))->everyMinute();

    Str::createUuidsUsing(fn () => '00000000-0000-0000-0000-000000000001');
    Artisan::call('schedule:run');

    $this->ingest->assertWrittenTimes(1);
    $this->ingest->assertLatestWrite('scheduled-task:0.trace_id', '00000000-0000-0000-0000-000000000001');
    $this->ingest->assertLatestWrite('scheduled-task:0.timestamp', 946688523.456789);
    $this->ingest->flush();

    Str::createUuidsUsing(fn () => '00000000-0000-0000-0000-000000000002');
    Artisan::call('schedule:run');

    $this->ingest->assertWrittenTimes(1);
    $this->ingest->assertLatestWrite('scheduled-task:0.trace_id', '00000000-0000-0000-0000-000000000002');
    $this->ingest->assertLatestWrite('scheduled-task:0.timestamp', 946688524.456789);
});

describe('task name normalization', function () {
    it('normalizes task name for named closure', function () {
        Schedule::call(fn () => travelTo(now()->addMicroseconds(1_000_000)))
            ->name('named-closure')
            ->everyMinute();

        Artisan::call('schedule:run');

        $this->ingest->assertWrittenTimes(1);
        $this->ingest->assertLatestWrite('scheduled-task:0.name', 'named-closure');
    });

    it('normalizes task name for invokable class', function () {
        class ProcessFlights
        {
            public function __invoke()
            {
                //
            }
        }

        Schedule::call(new ProcessFlights)->everyMinute();

        Artisan::call('schedule:run');

        $this->ingest->assertWrittenTimes(1);
        $this->ingest->assertLatestWrite('scheduled-task:0.name', 'ProcessFlights');
    });

    it('normalizes task name for artisan command', function () {
        Artisan::command('app:fly {destination} {--force} {--compress}', function () {
            //
        });

        Schedule::command('app:fly tokyo')->everyMinute();

        Artisan::call('schedule:run');

        $this->ingest->assertWrittenTimes(1);
        $this->ingest->assertLatestWrite('scheduled-task:0.name', 'php artisan app:fly tokyo');
    });

    it('normalizes task name for queued job', function () {
        class GenerateReport implements ShouldQueue
        {
            use Queueable;

            public function handle()
            {
                //
            }
        }

        Schedule::job(new GenerateReport)->everyMinute();

        Artisan::call('schedule:run');

        $this->ingest->assertWrittenTimes(1);
        $this->ingest->assertLatestWrite('scheduled-task:0.name', 'GenerateReport');
    });

    it('normalizes task name for job class method call', function () {
        class GenerateInvoice implements ShouldQueue
        {
            use Queueable;

            public function handle()
            {
                //
            }
        }

        Schedule::call([new GenerateInvoice, 'handle']);

        Artisan::call('schedule:run');

        $this->ingest->assertWrittenTimes(1);
        $this->ingest->assertLatestWrite('scheduled-task:0.name', 'GenerateInvoice');
    });

    it('normalizes task name for shell command', function () {
        Schedule::exec('find ./storage/logs -type f -mtime +7 -delete')->everyMinute();

        Artisan::call('schedule:run');

        $this->ingest->assertWrittenTimes(1);
        $this->ingest->assertLatestWrite('scheduled-task:0.name', 'find ./storage/logs -type f -mtime +7 -delete');
    });
});
