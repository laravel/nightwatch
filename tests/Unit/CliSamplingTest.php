<?php

namespace Tests\Unit;

use App\Jobs\MyJob;
use App\Jobs\SampledJob;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\WithConsoleEvents;
use Illuminate\Support\Facades\Artisan;
use Laravel\Nightwatch\Compatibility;
use Laravel\Nightwatch\Console\Sample;
use Laravel\Nightwatch\Facades\Nightwatch;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Tests\TestCase;

use function event;

class CliSamplingTest extends TestCase
{
    use WithConsoleEvents;

    protected function setUp(): void
    {
        $this->forceCommandExecutionState();

        parent::setUp();
    }

    public function test_it_samples_job_attempts(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['exceptions'] = 0.0;
        Compatibility::addSamplingToContext(false);

        for ($i = 0; $i < 10; $i++) {
            MyJob::dispatch();
        }
        Artisan::call('queue:work', [
            '--max-jobs' => 10,
            '--sleep' => 0,
            '--stop-when-empty' => true,
            '--tries' => 1,
        ]);

        $ingest->assertWrittenTimes(0);
        $this->assertCount(0, $this->core->ingest->buffer);

        Compatibility::addSamplingToContext(true);

        for ($i = 0; $i < 10; $i++) {
            MyJob::dispatch();
        }
        Artisan::call('queue:work', [
            '--max-jobs' => 10,
            '--sleep' => 0,
            '--stop-when-empty' => true,
            '--tries' => 1,
        ]);

        $ingest->assertWrittenTimes(10);

        for ($i = 1; $i < 10; $i++) {
            $ingest->assertWrite($i, 'job-attempt:0.name', 'App\Jobs\MyJob');
        }

        $this->assertCount(0, $this->core->ingest->buffer);
    }

    public function test_it_can_dynamically_set_sample_rate(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['exceptions'] = 0.0;
        Compatibility::addSamplingToContext(false);

        for ($i = 0; $i < 100; $i++) {
            SampledJob::dispatch(0.0);
        }

        Artisan::call('queue:work', [
            '--max-jobs' => 100,
            '--sleep' => 0,
            '--stop-when-empty' => true,
            '--tries' => 1,
        ]);

        $ingest->assertWrittenTimes(0);
        $this->assertCount(0, $this->core->ingest->buffer);
        $ingest->forgetWrites();

        for ($i = 0; $i < 100; $i++) {
            SampledJob::dispatch(0.5);
        }
        Artisan::call('queue:work', [
            '--max-jobs' => 100,
            '--sleep' => 0,
            '--stop-when-empty' => true,
            '--tries' => 1,
        ]);

        $this->assertEqualsWithDelta(50, $ingest->writes()->count(), 20);
        $ingest->forgetWrites();

        for ($i = 0; $i < 100; $i++) {
            SampledJob::dispatch(1.0);
        }
        Artisan::call('queue:work', [
            '--max-jobs' => 100,
            '--sleep' => 0,
            '--stop-when-empty' => true,
            '--tries' => 1,
        ]);

        $ingest->assertWrittenTimes(100);
        $this->assertCount(0, $this->core->ingest->buffer);
    }

    public function test_it_pulls_sample_from_context_when_command_starting(): void
    {
        Compatibility::addSamplingToContext(false);

        $this->assertTrue(Nightwatch::sampling());
        event(new CommandStarting('schedule:run', new StringInput(''), new NullOutput));
        $this->assertFalse(Nightwatch::sampling());
    }

    public function test_it_resets_sampling_after_each_task(): void
    {
        event(new CommandStarting('schedule:run', new StringInput(''), new NullOutput));

        Nightwatch::dontSample();
        event(new ScheduledTaskStarting($this->app[Schedule::class]->call('php artisan inspire')));

        $this->assertTrue(Nightwatch::sampling());
    }

    public function test_it_can_use_global_config_to_sample_scheduled_tasks(): void
    {
        $ingest = $this->fakeIngest();

        $this->core->config['sampling']['scheduled_tasks'] = 0.5;

        $this->app[Schedule::class]->call(fn () => 'schedule 1')->everyMinute();

        $writes = 0;
        for ($i = 0; $i < 100; $i++) {
            Artisan::call('schedule:run');
            $writes += $ingest->writes()->count();
            $ingest->forgetWrites();
        }

        $this->assertEqualsWithDelta(50, $writes, 10);
        $this->assertCount(0, $this->core->ingest->buffer);
    }

    public function test_it_applies_individual_sample_rates_to_scheduled_tasks(): void
    {
        $ingest = $this->fakeIngest();

        $this->core->config['sampling']['scheduled_tasks'] = 0.5;

        $this->app[Schedule::class]->call(fn () => 'schedule 1')->everyMinute()->tap(Sample::never());
        $this->app[Schedule::class]->call(fn () => 'schedule 2')->everyMinute()->tap(Sample::always())->description('schedule 2');

        $writes = 0;
        for ($i = 0; $i < 100; $i++) {
            Artisan::call('schedule:run');
            $ingest->assertLatestWrite('scheduled-task:0.name', 'schedule 2');
            $writes += $ingest->writes()->count();
            $ingest->forgetWrites();
        }

        $this->assertSame(100, $writes);
        $this->assertCount(0, $this->core->ingest->buffer);
    }
}
