<?php

namespace Tests\Unit;

use Exception;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Illuminate\Support\Facades\Event;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Records\JobAttempt;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\NullOutput;
use Tests\FakeJob;
use Tests\TestCase;

use function str_replace;

class FilteringCommandTest extends TestCase
{
    protected function setUp(): void
    {
        $this->forceCommandExecutionState();

        parent::setUp();
    }

    public function test_it_can_ignore_job_attempts(): void
    {
        Event::dispatch(new CommandStarting('queue:work', new ArgvInput, new NullOutput));

        $this->core->config['filtering']['ignore_job_attempts'] = true;

        for ($i = 0; $i < 10; $i++) {

            Event::dispatch(
                new JobProcessed(
                    'forget',
                    new FakeJob
                )
            );
        }

        $this->assertSame(0, $this->core->executionState->jobsAttempted);

        $this->core->config['filtering']['ignore_job_attempts'] = false;

        for ($i = 0; $i < 10; $i++) {

            Event::dispatch(
                new JobProcessed(
                    'forget',
                    new FakeJob
                )
            );
        }

        $this->assertSame(10, $this->core->executionState->jobsAttempted);
    }

    public function test_it_can_filter_job_attempts(): void
    {
        Event::dispatch(new CommandStarting('queue:work', new ArgvInput, new NullOutput));

        $ingest = $this->fakeIngest();
        Nightwatch::rejectJobAttempts(function (JobAttempt $jobEvent) {
            return $jobEvent->exception === null;
        });

        Event::dispatch(
            new JobProcessed(
                'forget',
                new FakeJob
            )
        );

        Event::dispatch(
            new JobReleasedAfterException(
                'forget',
                new FakeJob
            )
        );

        Event::dispatch(
            new JobFailed(
                'keep',
                new FakeJob,
                new Exception('User 123 not found')
            )
        );

        $ingest->digest();

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(1, $records);

            return true;
        });
        $ingest->assertLatestWrite('job-attempt:0.exception', 'User 123 not found');
    }

    public function test_it_can_redact_job_attempts(): void
    {
        Event::dispatch(new CommandStarting('queue:work', new ArgvInput, new NullOutput));

        $ingest = $this->fakeIngest();
        Nightwatch::redactJobAttempts(function (JobAttempt $jobEvent) {
            if ($jobEvent->exception !== null) {
                $jobEvent->exception = new Exception(
                    str_replace('123', '***', $jobEvent->exception->getMessage()),
                    $jobEvent->exception->getCode(),
                    $jobEvent->exception
                );
            }
        });

        Event::dispatch(
            new JobProcessed(
                'taylor@laravel.com|127.0.0.1',
                new FakeJob
            )
        );

        Event::dispatch(
            new JobReleasedAfterException(
                'tim@laravel.com|127.0.0.1',
                new FakeJob
            )
        );

        Event::dispatch(
            new JobFailed(
                'jess@laravel.com|127.0.0.1',
                new FakeJob,
                new Exception('User 123 not found')
            )
        );

        $ingest->digest();

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('job-attempt:2.exception', 'User *** not found');
    }
}
