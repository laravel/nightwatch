<?php

namespace Tests\Feature\Sensors;

use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Testing\WithConsoleEvents;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

use function dirname;
use function hash;
use function now;

class ScheduledTaskSensorTest extends TestCase
{
    use WithConsoleEvents;

    protected function setUp(): void
    {
        $this->forceCommandExecutionState();

        parent::setUp();

        $this->setDeploy('v1.2.3');
        $this->setServerName('scheduler-01');
        $this->setPeakMemory(1234);
        $this->setExecutionStart(CarbonImmutable::parse('2000-01-01 01:02:03.456789'));
        // --- //
        $this->core->uuid->uuidResolver = fn () => '00000000-0000-0000-0000-000000000000';
        $this->app->setBasePath(dirname($this->app->basePath()));
    }

    public function test_it_ingests_processed_tasks(): void
    {
        $ingest = $this->fakeIngest();
        $line = __LINE__ + 1;
        $task = $this->app[Schedule::class]->call(fn () => $this->travelTo(now()->addMicroseconds(1_000_000)))->everyMinute();
        $name = "Closure at: tests/Feature/Sensors/ScheduledTaskSensorTest.php:{$line}";

        Artisan::call('schedule:run');

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('scheduled-task:*', [
            [
                'v' => 1,
                't' => 'scheduled-task',
                'timestamp' => 946688523.456789,
                'deploy' => 'v1.2.3',
                'server' => 'scheduler-01',
                '_group' => hash('xxh128', "{$name},{$task->expression},{$task->timezone}"),
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
                'exception_preview' => '',
            ],
        ]);
    }

    public function test_it_ingests_skipped_tasks(): void
    {
        $ingest = $this->fakeIngest();
        $line = __LINE__ + 1;
        $task = $this->app[Schedule::class]->call(fn () => $this->travelTo(now()->addMicroseconds(1_000_000)))
            ->skip(fn () => true)
            ->everyMinute();
        $name = "Closure at: tests/Feature/Sensors/ScheduledTaskSensorTest.php:{$line}";

        Artisan::call('schedule:run');

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('scheduled-task:*', [
            [
                'v' => 1,
                't' => 'scheduled-task',
                'timestamp' => 946688523.456789,
                'deploy' => 'v1.2.3',
                'server' => 'scheduler-01',
                '_group' => hash('xxh128', "{$name},{$task->expression},{$task->timezone}"),
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
                'exception_preview' => '',
            ],
        ]);
    }

    public function test_it_ingests_failed_tasks(): void
    {
        $ingest = $this->fakeIngest();
        $line = __LINE__ + 1;
        $task = $this->app[Schedule::class]->call(function (): void {
            $this->travelTo(now()->addMicroseconds(1_000_000));

            throw new Exception('Unhandled error');
        })->everyMinute();
        $name = "Closure at: tests/Feature/Sensors/ScheduledTaskSensorTest.php:{$line}";

        Artisan::call('schedule:run');

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('scheduled-task:*', [
            [
                'v' => 1,
                't' => 'scheduled-task',
                'timestamp' => 946688523.456789,
                'deploy' => 'v1.2.3',
                'server' => 'scheduler-01',
                '_group' => hash('xxh128', "{$name},{$task->expression},{$task->timezone}"),
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
                'exception_preview' => 'Unhandled error',
            ],
        ]);
        $ingest->assertLatestWrite('exception:0.message', 'Unhandled error');
    }

    public function test_it_resets_trace_i_d_and_timestamp_on_each_task_run(): void
    {
        $ingest = $this->fakeIngest();
        $this->app[Schedule::class]->call(fn () => $this->travelTo(now()->addMicroseconds(1_000_000)))->everyMinute();

        $this->core->uuid->uuidResolver = fn () => '00000000-0000-0000-0000-000000000001';
        Artisan::call('schedule:run');

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('scheduled-task:0.trace_id', '00000000-0000-0000-0000-000000000001');
        $ingest->assertLatestWrite('scheduled-task:0.timestamp', 946688523.456789);

        $this->core->uuid->uuidResolver = fn () => '00000000-0000-0000-0000-000000000002';
        Artisan::call('schedule:run');

        $ingest->assertWrittenTimes(2);
        $ingest->assertLatestWrite('scheduled-task:0.trace_id', '00000000-0000-0000-0000-000000000002');
        $ingest->assertLatestWrite('scheduled-task:0.timestamp', 946688524.456789);
    }

    public function test_it_normalizes_task_name_for_named_closure(): void
    {
        $ingest = $this->fakeIngest();
        $this->app[Schedule::class]->call(fn () => $this->travelTo(now()->addMicroseconds(1_000_000)))
            ->name('named-closure')
            ->everyMinute();

        Artisan::call('schedule:run');

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('scheduled-task:0.name', 'named-closure');
    }

    public function test_it_normalizes_task_name_for_invokable_class(): void
    {
        $ingest = $this->fakeIngest();
        $this->app[Schedule::class]->call(new ProcessFlights)->everyMinute();

        Artisan::call('schedule:run');

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('scheduled-task:0.name', 'Tests\Feature\Sensors\ProcessFlights');
    }

    public function test_it_normalizes_task_name_for_artisan_command(): void
    {
        $ingest = $this->fakeIngest();
        Artisan::command('app:fly {destination} {--force} {--compress}', function (): void {
            //
        });

        $this->app[Schedule::class]->command('app:fly tokyo')->everyMinute();

        Artisan::call('schedule:run');

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('scheduled-task:0.name', 'php artisan app:fly tokyo');
    }

    public function test_it_normalizes_task_name_for_queued_job(): void
    {
        $ingest = $this->fakeIngest();
        $this->app[Schedule::class]->job(new GenerateReport)->everyMinute();

        Artisan::call('schedule:run');

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('scheduled-task:0.name', 'Tests\Feature\Sensors\GenerateReport');
    }

    public function test_it_normalizes_task_name_for_job_class_method_call(): void
    {
        $ingest = $this->fakeIngest();
        $this->app[Schedule::class]->call([new GenerateInvoice, 'handle']);

        Artisan::call('schedule:run');

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('scheduled-task:0.name', 'Tests\Feature\Sensors\GenerateInvoice');
    }

    public function test_it_normalizes_task_name_for_shell_command(): void
    {
        $ingest = $this->fakeIngest();
        $this->app[Schedule::class]->exec('find ./storage/logs -type f -mtime +7 -delete')->everyMinute();

        Artisan::call('schedule:run');

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('scheduled-task:0.name', 'find ./storage/logs -type f -mtime +7 -delete');
    }
}

class ProcessFlights
{
    public function __invoke(): void
    {
        //
    }
}

class GenerateReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        //
    }
}

class GenerateInvoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        //
    }
}
