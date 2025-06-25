<?php

namespace Tests\Unit;

use App\Jobs\MyJob;
use App\Jobs\SampledJob;
use Illuminate\Foundation\Testing\WithConsoleEvents;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Records\Command;
use Laravel\Nightwatch\Records\JobAttempt;
use Laravel\Nightwatch\Records\ScheduledTask;
use Symfony\Component\Console\Input\StringInput;
use Tests\TestCase;

use function array_shift;

class CliFilteringTest extends TestCase
{
    use WithConsoleEvents;

    protected function setUp(): void
    {
        $this->forceCommandExecutionState();

        parent::setUp();
    }

    public function test_it_can_filter_commands(): void
    {
        $ingest = $this->fakeIngest();
        Artisan::command('first', function () {
            DB::statement('select * from users');
        });
        Artisan::command('second', function () {
            DB::statement('select * from jobs');
        });
        $keep = [true, false];
        Nightwatch::interceptCommands(function (Command $command) use (&$keep) {
            return array_shift($keep);
        });

        $status = Artisan::handle($input = new StringInput('first'));
        Artisan::terminate($input, $status);

        $this->assertTrue($this->core->sampling());

        $status = Artisan::handle($input = new StringInput('second'));
        Artisan::terminate($input, $status);

        $this->assertFalse($this->core->sampling());

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(2, $records);

            return true;
        });
        $ingest->assertLatestWrite('command:0.name', 'first');
        $ingest->assertLatestWrite('query:0.sql', 'select * from users');
    }

    public function test_it_can_filter_job_attempts(): void
    {
        $ingest = $this->fakeIngest();
        Nightwatch::interceptJobAttempts(function (JobAttempt $jobAttempt) {
            return $jobAttempt->name === SampledJob::class;
        });

        SampledJob::dispatch(1);
        MyJob::dispatch();
        $this->core->flush();

        Artisan::call('queue:work', [
            '--max-jobs' => 1,
            '--sleep' => 0,
            '--stop-when-empty' => true,
            '--tries' => 1,
        ]);

        $this->assertTrue($this->core->sampling());

        Artisan::call('queue:work', [
            '--max-jobs' => 1,
            '--sleep' => 0,
            '--stop-when-empty' => true,
            '--tries' => 1,
        ]);

        $this->assertFalse($this->core->sampling());

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(6, $records);

            return true;
        });
        $ingest->assertLatestWrite('job-attempt:0.name', SampledJob::class);
        $ingest->assertLatestWrite('cache-event:*', function ($queries) {
            $this->assertCount(1, $queries);

            return true;
        });
        $ingest->assertLatestWrite('query:*', function ($queries) {
            $this->assertCount(4, $queries);

            return true;
        });
    }

    public function test_it_can_filter_scheduled_tasks(): void
    {
        $ingest = $this->fakeIngest();
        Schedule::call(function () {
            DB::statement('select * from users');
        })->name('first');
        Schedule::call(function () {
            DB::statement('select * from jobs');
        })->name('second');
        Nightwatch::interceptScheduledTasks(function (ScheduledTask $scheduledTask) {
            return $scheduledTask->name === 'first';
        });

        Artisan::call('schedule:run');

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(2, $records);

            return true;
        });
        $ingest->assertLatestWrite('scheduled-task:0.name', 'first');
        $ingest->assertLatestWrite('query:0.sql', 'select * from users');
    }
}
