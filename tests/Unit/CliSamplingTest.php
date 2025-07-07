<?php

namespace Tests\Unit;

use App\Jobs\MyJob;
use App\Jobs\SampledJob;
use Illuminate\Foundation\Testing\WithConsoleEvents;
use Illuminate\Support\Facades\Artisan;
use Laravel\Nightwatch\Compatibility;
use Tests\TestCase;

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
}
