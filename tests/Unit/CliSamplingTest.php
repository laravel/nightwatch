<?php

namespace Tests\Unit;

use App\Jobs\MyJob;
use App\Jobs\SampledJob;
use Illuminate\Foundation\Testing\WithConsoleEvents;
use Illuminate\Support\Facades\Artisan;
use Laravel\Nightwatch\Compatibility;
use RuntimeException;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Tests\FakeJob;
use Tests\TestCase;

use function collect;
use function json_decode;
use function report;

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
        Compatibility::addHiddenContext('nightwatch_should_sample', false);

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

        Compatibility::addHiddenContext('nightwatch_should_sample', true);

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
        Compatibility::addHiddenContext('nightwatch_should_sample', false);

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

        $this->assertEqualsWithDelta(50, $ingest->writes()->count(), 8);
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

    public function test_it_use_global_config_to_sample_commands(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['commands'] = 0;

        for ($i = 0; $i < 100; $i++) {
            $this->artisan('inspire')->assertExitCode(0);
        }

        $ingest->assertWrittenTimes(0);
        $this->assertCount(0, $this->core->ingest->buffer);

        $this->core->config['sampling']['commands'] = 0.25;
        $writes = 0;

        for ($i = 0; $i < 100; $i++) {
            $this->artisan('inspire')->assertExitCode(0);
            $writes += $ingest->writes()->count();
            $ingest->forgetWrites();
        }

        $this->assertEqualsWithDelta(25, $writes, 8);
        $this->assertCount(0, $this->core->ingest->buffer);

        $this->core->config['sampling']['commands'] = 0.5;
        $writes = 0;

        for ($i = 0; $i < 100; $i++) {
            $this->artisan('inspire')->assertExitCode(0);
            $writes += $ingest->writes()->count();
            $ingest->forgetWrites();
        }

        $this->assertEqualsWithDelta(50, $sampled, 8);
        $this->assertCount(0, $this->core->ingest->buffer);

        $this->core->config['sampling']['commands'] = 1.0;
        $writes = 0;

        for ($i = 0; $i < 100; $i++) {
            $this->artisan('inspire')->assertExitCode(0);
            $writes += $ingest->writes()->count();
            $ingest->forgetWrites();
        }

        $this->assertSame(100, $writes);
        $this->assertCount(0, $this->core->ingest->buffer);
    }

    public function test_it_can_set_sample_rate_for_commands_to_capture_events_after_exception_occurs_when_not_sampling_unless_exception_occurs(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['commands'] = 0;

        $this->core->config['sampling']['exceptions'] = 0;
        $sampled = 0;

        for ($i = 0; $i < 1000; $i++) {
            $this->core->configureGlobalCommandSampling();

            if ($this->core->shouldSampleOnException) {
                $sampled++;
            }
        }

        $this->assertSame(0, $sampled);

        $this->core->config['sampling']['exceptions'] = 0.25;
        $sampled = 0;

        for ($i = 0; $i < 1000; $i++) {
            $this->core->configureGlobalCommandSampling();

            if ($this->core->shouldSampleOnException) {
                $sampled++;
            }
        }

        $this->assertEqualsWithDelta(250, $sampled, 50);

        $this->core->config['sampling']['exceptions'] = 0.5;
        $sampled = 0;

        for ($i = 0; $i < 1000; $i++) {
            $this->core->configureGlobalCommandSampling();

            if ($this->core->shouldSampleOnException) {
                $sampled++;
            }
        }

        $this->assertEqualsWithDelta(500, $sampled, 50);

        $this->core->config['sampling']['exceptions'] = 0.75;
        $sampled = 0;

        for ($i = 0; $i < 1000; $i++) {
            $this->core->configureGlobalCommandSampling();

            if ($this->core->shouldSampleOnException) {
                $sampled++;
            }
        }

        $this->assertEqualsWithDelta(750, $sampled, 50);

        $this->core->config['sampling']['exceptions'] = 1.0;
        $sampled = 0;

        for ($i = 0; $i < 1000; $i++) {
            $this->core->configureGlobalCommandSampling();

            if ($this->core->shouldSampleOnException) {
                $sampled++;
            }
        }

        $this->assertSame(1000, $sampled);
    }

    public function test_it_can_set_sample_rate_for_jobs_to_capture_events_after_exception_occurs_when_not_sampling_unless_exception_occurs(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['exceptions'] = 0;
        $sampled = 0;

        for ($i = 0; $i < 1000; $i++) {
            $this->core->prepareForJob(new FakeJob);

            if ($this->core->shouldSampleOnException) {
                $sampled++;
            }
        }

        $this->assertSame(0, $sampled);

        $this->core->config['sampling']['exceptions'] = 0.25;
        $sampled = 0;

        for ($i = 0; $i < 1000; $i++) {
            $this->core->prepareForJob(new FakeJob);

            if ($this->core->shouldSampleOnException) {
                $sampled++;
            }
        }

        $this->assertEqualsWithDelta(250, $sampled, 50);

        $this->core->config['sampling']['exceptions'] = 0.5;
        $sampled = 0;

        for ($i = 0; $i < 1000; $i++) {
            $this->core->prepareForJob(new FakeJob);

            if ($this->core->shouldSampleOnException) {
                $sampled++;
            }
        }

        $this->assertEqualsWithDelta(500, $sampled, 50);

        $this->core->config['sampling']['exceptions'] = 0.75;
        $sampled = 0;

        for ($i = 0; $i < 1000; $i++) {
            $this->core->prepareForJob(new FakeJob);

            if ($this->core->shouldSampleOnException) {
                $sampled++;
            }
        }

        $this->assertEqualsWithDelta(750, $sampled, 50);

        $this->core->config['sampling']['exceptions'] = 1.0;
        $sampled = 0;

        for ($i = 0; $i < 1000; $i++) {
            $this->core->prepareForJob(new FakeJob);

            if ($this->core->shouldSampleOnException) {
                $sampled++;
            }
        }

        $this->assertSame(1000, $sampled);
    }

    public function test_it_samples_preparing_for_command(): void
    {
        $this->core->sampling(false);
        $this->core->shouldSampleOnException = false;

        $this->core->executionState->name = 'previous';
        $this->core->executionState->executionPreview = 'previous';

        $this->core->prepareForCommand('current');

        $this->assertSame('previous', $this->core->executionState->name);
        $this->assertSame('previous', $this->core->executionState->executionPreview);

        $this->core->sampling(true);

        $this->core->prepareForCommand('current');

        $this->assertSame('current', $this->core->executionState->name);
        $this->assertSame('current', $this->core->executionState->executionPreview);
    }

    public function test_it_prepares_for_command_when_not_sampling_unless_exception_occurs(): void
    {
        $this->core->sampling(false);
        $this->core->config['sampling']['exceptions'] = 1.0;

        $this->core->executionState->name = 'previous';
        $this->core->executionState->executionPreview = 'previous';

        $this->core->prepareForCommand('current');

        $this->assertSame('current', $this->core->executionState->name);
        $this->assertSame('current', $this->core->executionState->executionPreview);
    }

    public function test_it_samples_commands(): void
    {
        Artisan::command('app:build', function () {
            return 0;
        });
        $this->core->config['sampling']['commands'] = 0;
        $this->core->configureGlobalCommandSampling();

        // bootstrap the test to ensure everything needed is in place, such as artisan
        Artisan::handle($input = new StringInput('app:build'));

        for ($i = 0; $i < 10; $i++) {
            $this->core->prepareForCommand('app:build');
            $this->core->command($input, 0);
        }

        $this->assertSame('[]', $this->core->ingest->buffer->pull()->rawPayload());

        $this->core->config['sampling']['commands'] = 1.0;
        $this->core->configureGlobalCommandSampling();

        for ($i = 0; $i < 10; $i++) {
            $this->core->prepareForCommand('app:build');
            $this->core->command($input, 0);
        }

        $commands = collect(json_decode($this->core->ingest->buffer->pull()->rawPayload()));
        $this->assertCount(10, $commands);
        $this->assertTrue($commands->pluck('name')->every(fn ($name) => $name === 'app:build'));
    }

    public function test_it_can_captures_commands_after_exception_occurs_when_not_sampling_unless_exception_occurs(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['commands'] = 0;
        $this->core->configureGlobalCommandSampling();
        $this->core->config['sampling']['exceptions'] = 1.0;
        Artisan::command('app:build', function () {
            report(new RuntimeException('Whoops!'));

            return 8;
        });

        $status = Artisan::handle(
            $input = new StringInput('app:build'),
            new ConsoleOutput
        );
        Artisan::terminate($input, $status);

        $this->assertSame(8, $status);
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(2, $records);

            return true;
        });
        $ingest->assertLatestWrite('exception:0.message', 'Whoops!');
        $ingest->assertLatestWrite('command:0.name', 'app:build');
    }
}
