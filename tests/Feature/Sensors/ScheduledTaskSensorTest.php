<?php

namespace Tests\Feature\Sensors;

use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Duration;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Testing\WithConsoleEvents;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Sleep;
use Laravel\Nightwatch\Compatibility;
use Orchestra\Testbench\Attributes\WithConfig;
use Tests\TestCase;

use function collect;
use function dirname;
use function hash;
use function json_decode;
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
                'repeat_seconds' => 0,
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
                'context' => Compatibility::$contextExists ? '{}' : '',
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
                'repeat_seconds' => 0,
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
                'peak_memory_usage' => 1234,
                'exception_preview' => '',
                'context' => Compatibility::$contextExists ? '{}' : '',
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
                'repeat_seconds' => 0,
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
                'context' => Compatibility::$contextExists ? '{}' : '',
            ],
        ]);
        $ingest->assertLatestWrite('exception:0.message', 'Unhandled error');
    }

    public function test_it_ingests_tasks_run_in_background(): void
    {
        $ingest = $this->fakeIngest();
        Artisan::command('app:fly {destination} {--force} {--compress}', function (): void {
            $this->travelTo(now()->addMicroseconds(1_000_000));
        });

        $task = $this->app[Schedule::class]->command('app:fly tokyo')->everyMinute()->runInBackground();

        Artisan::call('schedule:run');

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('scheduled-task:*', [
            [
                'v' => 1,
                't' => 'scheduled-task',
                'timestamp' => 946688523.456789,
                'deploy' => 'v1.2.3',
                'server' => 'scheduler-01',
                '_group' => hash('xxh128', "php artisan app:fly tokyo,{$task->expression},{$task->timezone}"),
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'name' => 'php artisan app:fly tokyo',
                'cron' => '* * * * *',
                'timezone' => 'UTC',
                'repeat_seconds' => 0,
                'without_overlapping' => false,
                'on_one_server' => false,
                'run_in_background' => true,
                'even_in_maintenance_mode' => false,
                'status' => 'processed',
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
                'peak_memory_usage' => 1234,
                'exception_preview' => '',
                'context' => Compatibility::$contextExists ? '{}' : '',
            ],
        ]);
    }

    public function test_it_ingests_subminute_tasks(): void
    {
        $ingest = $this->fakeIngest();
        $line = __LINE__ + 1;
        $task = $this->app[Schedule::class]->call(fn () => $this->travelTo(now()->addMicroseconds(30_000_000)))->everyThirtySeconds();
        $name = "Closure at: tests/Feature/Sensors/ScheduledTaskSensorTest.php:{$line}";
        $repeatSeconds = Compatibility::$subMinuteScheduledTasksSupported ? 30 : 0;

        Artisan::call('schedule:run');

        $ingest->assertWrittenTimes(2);

        $ingest->assertWrite(0, 'scheduled-task:*', [
            [
                'v' => 1,
                't' => 'scheduled-task',
                'timestamp' => 946688523.456789,
                'deploy' => 'v1.2.3',
                'server' => 'scheduler-01',
                '_group' => hash('xxh128', "{$name},{$task->expression},{$task->timezone},{$repeatSeconds}"),
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'name' => $name,
                'cron' => '* * * * *',
                'timezone' => 'UTC',
                'repeat_seconds' => Compatibility::$subMinuteScheduledTasksSupported ? 30 : 0,
                'without_overlapping' => false,
                'on_one_server' => false,
                'run_in_background' => false,
                'even_in_maintenance_mode' => false,
                'status' => 'processed',
                'duration' => 30_000_000,
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
                'context' => Compatibility::$contextExists ? '{}' : '',
            ],
        ]);

        $ingest->assertWrite(1, 'scheduled-task:*', [
            [
                'v' => 1,
                't' => 'scheduled-task',
                'timestamp' => 946688553.456789,
                'deploy' => 'v1.2.3',
                'server' => 'scheduler-01',
                '_group' => hash('xxh128', "{$name},{$task->expression},{$task->timezone},{$repeatSeconds}"),
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'name' => $name,
                'cron' => '* * * * *',
                'timezone' => 'UTC',
                'repeat_seconds' => $repeatSeconds,
                'without_overlapping' => false,
                'on_one_server' => false,
                'run_in_background' => false,
                'even_in_maintenance_mode' => false,
                'status' => 'processed',
                'duration' => 30_000_000,
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
                'context' => Compatibility::$contextExists ? '{}' : '',
            ],
        ]);
    }

    #[WithConfig('cache.default', 'database')]
    public function test_it_does_not_digest_records_between_sub_minute_scheduled_tasks(): void
    {
        $this->travelTo(now()->startOfMinute());
        $ingest = $this->fakeIngest();

        Sleep::fake();
        Sleep::whenFakingSleep(function (Duration $duration) {
            $this->travel($duration->totalMilliseconds)->milliseconds();
        });

        $line = __LINE__ + 1;
        $task = $this->app[Schedule::class]->call(fn () => null)->everyThirtySeconds();
        $name = "Closure at: tests/Feature/Sensors/ScheduledTaskSensorTest.php:{$line}";

        Artisan::call('schedule:run');

        $ingest->assertWrittenTimes(2);

        $ingest->decodedWrites()->each(function ($write) use ($name) {
            collect($write)->each(function ($record) use ($name) {
                $this->assertSame('scheduled-task', $record['t']);
                $this->assertSame($name, $record['name']);
            });
        });
    }

    public function test_it_resets_trace_id_and_timestamp_on_each_task_run(): void
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

    public function test_it_captures_context(): void
    {
        $this->markTestSkippedUnless(Compatibility::$contextExists, 'This test requires the Laravel Context.');

        $ingest = $this->fakeIngest();
        $model = User::factory()->create();
        $this->app[Schedule::class]->call(function () use ($model) {
            Context::add('string', 'value');
            Context::add('integer', 123);
            Context::add('float', 123.456);
            Context::add('boolean', true);
            Context::add('null', null);
            Context::add('list', [1, 2.0, 'three']);
            Context::add('associative', ['key' => 'value']);
            Context::add('object', (object) ['key' => 'value']);
            Context::add('model', $model);
        })->everyMinute();

        Artisan::call('schedule:run');

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('scheduled-task:0.context', function ($context) use ($model) {
            $context = json_decode($context, true);
            $this->assertSame('value', $context['string']);
            $this->assertSame(123, $context['integer']);
            $this->assertSame(123.456, $context['float']);
            $this->assertTrue($context['boolean']);
            $this->assertNull($context['null']);
            $this->assertSame([1, 2.0, 'three'], $context['list']);
            $this->assertSame(['key' => 'value'], $context['associative']);
            $this->assertSame(['key' => 'value'], $context['object']);
            $this->assertSame($model->getKey(), $context['model']['id']);

            return true;
        });
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
