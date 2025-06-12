<?php

namespace Tests\Unit;

use App\Jobs\MyJob;
use Illuminate\Foundation\Testing\WithConsoleEvents;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Compatibility;
use RuntimeException;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Tests\FakeJob;
use Tests\TestCase;

use function basename;
use function collect;
use function dispatch;
use function json_decode;
use function json_encode;
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

    public function test_it_can_captures_job_attempts_after_exception_occurs_when_not_sampling_unless_exception_occurs(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['exceptions'] = 1.0;
        Compatibility::addHiddenContext('nightwatch_should_sample', false);

        $line = __LINE__ + 1;
        dispatch(function () {
            throw new RuntimeException('Whoops!');
        });
        Artisan::call('queue:work', [
            '--max-jobs' => 1,
            '--sleep' => 0,
            '--stop-when-empty' => true,
            '--tries' => 1,
        ]);

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(8, $records);

            return true;
        });
        $ingest->assertLatestWrite('query:0.sql', 'select * from "jobs" where "queue" = ? and (("reserved_at" is null and "available_at" <= ?) or ("reserved_at" <= ?)) order by "id" asc limit 1');
        $ingest->assertLatestWrite('query:1.sql', 'update "jobs" set "reserved_at" = ?, "attempts" = ? where "id" = ?');
        $ingest->assertLatestWrite('query:2.sql', 'select * from "jobs" where "id" = ? limit 1');
        $ingest->assertLatestWrite('job-attempt:0.name', 'Closure ('.basename(__FILE__).':'.$line.')');
        $ingest->assertLatestWrite('exception:0.message', 'Whoops!');
        $ingest->assertLatestWrite('query:3.sql', 'delete from "jobs" where "id" = ?');
        $ingest->assertLatestWrite('query:4.sql', 'insert into "failed_jobs" ("uuid", "connection", "queue", "payload", "exception", "failed_at") values (?, ?, ?, ?, ?, ?)');
    }

    public function test_it_prepares_for_next_job(): void
    {
        $this->core->clock->microtimeResolver = fn () => 5.5;
        $this->core->executionState->setId('previous');
        $this->core->executionState->executionPreview = 'previous';
        $this->core->executionState->timestamp = 0.0;
        $this->core->config['sampling']['exceptions'] = 0.0;

        Compatibility::addHiddenContext('nightwatch_should_sample', false);
        $this->core->prepareForJob(new class extends FakeJob
        {
            public function resolveName()
            {
                return 'current';
            }
        });

        $this->assertSame('"previous"', json_encode($this->core->executionState->id()));
        $this->assertSame('previous', $this->core->executionState->executionPreview);
        $this->assertSame(0.0, $this->core->executionState->timestamp);

        Compatibility::addHiddenContext('nightwatch_should_sample', true);
        Str::createUuidsUsingSequence([
            '1CF1F203-73A5-4E9D-8662-12E1C712F130',
        ]);
        $this->core->prepareForJob(new class extends FakeJob
        {
            public function resolveName()
            {
                return 'current';
            }
        });

        $this->assertSame('"1CF1F203-73A5-4E9D-8662-12E1C712F130"', json_encode($this->core->executionState->id()));
        $this->assertSame('current', $this->core->executionState->executionPreview);
        $this->assertSame(5.5, $this->core->executionState->timestamp);
    }

    public function test_it_prepares_for_next_job_when_not_sampling_unless_exception_occurs(): void
    {
        $this->core->clock->microtimeResolver = fn () => 5.5;
        $this->core->executionState->setId('previous');
        $this->core->executionState->executionPreview = 'previous';
        $this->core->executionState->timestamp = 0.0;
        $this->core->config['sampling']['exceptions'] = 1.0;

        Compatibility::addHiddenContext('nightwatch_should_sample', false);
        Str::createUuidsUsingSequence([
            '1CF1F203-73A5-4E9D-8662-12E1C712F130',
        ]);
        $this->core->prepareForJob(new class extends FakeJob
        {
            public function resolveName()
            {
                return 'current';
            }
        });

        $this->assertSame('"1CF1F203-73A5-4E9D-8662-12E1C712F130"', json_encode($this->core->executionState->id()));
        $this->assertSame('current', $this->core->executionState->executionPreview);
        $this->assertSame(5.5, $this->core->executionState->timestamp);
    }

    public function test_it_can_configure_command_sampling(): void
    {
        $this->core->config['sampling']['commands'] = 0;
        $sampled = 0;

        for ($i = 0; $i < 1000; $i++) {
            $this->core->configureSampling('commands');
            if ($this->core->shouldSample) {
                $sampled++;
            }
        }

        $this->assertSame(0, $sampled);

        $this->core->config['sampling']['commands'] = 0.25;
        $sampled = 0;

        for ($i = 0; $i < 1000; $i++) {
            $this->core->configureSampling('commands');
            if ($this->core->shouldSample) {
                $sampled++;
            }
        }

        $this->assertEqualsWithDelta($sampled, 250, 50);

        $this->core->config['sampling']['commands'] = 0.5;
        $sampled = 0;

        for ($i = 0; $i < 1000; $i++) {
            $this->core->configureSampling('commands');
            if ($this->core->shouldSample) {
                $sampled++;
            }
        }

        $this->assertEqualsWithDelta($sampled, 500, 50);

        $this->core->config['sampling']['commands'] = 1.0;
        $sampled = 0;

        for ($i = 0; $i < 1000; $i++) {
            $this->core->configureSampling('commands');
            if ($this->core->shouldSample) {
                $sampled++;
            }
        }

        $this->assertSame(1000, $sampled);
    }

    public function test_it_can_set_sample_rate_for_commands_to_capture_events_after_exception_occurs_when_not_sampling_unless_exception_occurs(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['commands'] = 0;

        $this->core->config['sampling']['exceptions'] = 0;
        $sampled = 0;

        for ($i = 0; $i < 1000; $i++) {
            $this->core->configureSampling('commands');

            if ($this->core->shouldSampleOnException) {
                $sampled++;
            }
        }

        $this->assertSame(0, $sampled);

        $this->core->config['sampling']['exceptions'] = 0.25;
        $sampled = 0;

        for ($i = 0; $i < 1000; $i++) {
            $this->core->configureSampling('commands');

            if ($this->core->shouldSampleOnException) {
                $sampled++;
            }
        }

        $this->assertEqualsWithDelta(250, $sampled, 50);

        $this->core->config['sampling']['exceptions'] = 0.5;
        $sampled = 0;

        for ($i = 0; $i < 1000; $i++) {
            $this->core->configureSampling('commands');

            if ($this->core->shouldSampleOnException) {
                $sampled++;
            }
        }

        $this->assertEqualsWithDelta(500, $sampled, 50);

        $this->core->config['sampling']['exceptions'] = 0.75;
        $sampled = 0;

        for ($i = 0; $i < 1000; $i++) {
            $this->core->configureSampling('commands');

            if ($this->core->shouldSampleOnException) {
                $sampled++;
            }
        }

        $this->assertEqualsWithDelta(750, $sampled, 50);

        $this->core->config['sampling']['exceptions'] = 1.0;
        $sampled = 0;

        for ($i = 0; $i < 1000; $i++) {
            $this->core->configureSampling('commands');

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
        $this->core->shouldSample = false;
        $this->core->shouldSampleOnException = false;

        $this->core->executionState->name = 'previous';
        $this->core->executionState->executionPreview = 'previous';

        $this->core->prepareForCommand('current');

        $this->assertSame('previous', $this->core->executionState->name);
        $this->assertSame('previous', $this->core->executionState->executionPreview);

        $this->core->shouldSample = true;

        $this->core->prepareForCommand('current');

        $this->assertSame('current', $this->core->executionState->name);
        $this->assertSame('current', $this->core->executionState->executionPreview);
    }

    public function test_it_prepares_for_command_when_not_sampling_unless_exception_occurs(): void
    {
        $this->core->shouldSample = false;
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
        $this->core->configureSampling('commands');

        // bootstrap the test to ensure everything needed is in place, such as artisan
        Artisan::handle($input = new StringInput('app:build'));

        for ($i = 0; $i < 10; $i++) {
            $this->core->prepareForCommand('app:build');
            $this->core->command($input, 0);
        }

        $this->assertSame('[]', $this->core->ingest->buffer->pull()->rawPayload());

        $this->core->config['sampling']['commands'] = 1.0;
        $this->core->configureSampling('commands');

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
        $this->core->configureSampling('commands');
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
