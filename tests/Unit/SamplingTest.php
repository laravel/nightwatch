<?php

namespace Tests\Unit;

use App\Jobs\MyJob;
use App\Models\User as UserModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Nightwatch\Compatibility;
use RuntimeException;
use Tests\TestCase;

use function app;

class SamplingTest extends TestCase
{
    protected function setUp(): void
    {
        $this->forceRequestExecutionState();

        parent::setUp();
    }

    public function test_it_can_sample_requests(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['requests'] = 0;
        Route::get('/users', fn () => []);

        for ($i = 0; $i < 1000; $i++) {
            $this->get('/users');
            $this->app->forgetScopedInstances();
        }

        $ingest->assertWrittenTimes(0);
        $this->assertCount(0, $this->core->ingest->buffer);

        $this->core->config['sampling']['requests'] = 0.25;

        for ($i = 0; $i < 1000; $i++) {
            $this->get('/users');
            $this->app->forgetScopedInstances();
        }

        $this->assertEqualsWithDelta(250, $ingest->writes()->count(), 50);
        $this->assertCount(0, $this->core->ingest->buffer);
        $ingest->forgetWrites();

        $this->core->config['sampling']['requests'] = 0.5;

        for ($i = 0; $i < 1000; $i++) {
            $this->get('/users');
            $this->app->forgetScopedInstances();
        }

        $this->assertEqualsWithDelta(500, $ingest->writes()->count(), 50);
        $this->assertCount(0, $this->core->ingest->buffer);
        $ingest->forgetWrites();

        $this->core->config['sampling']['requests'] = 1.0;

        for ($i = 0; $i < 1000; $i++) {
            $this->get('/users');
            $this->app->forgetScopedInstances();
        }

        $this->assertEqualsWithDelta(1000, $ingest->writes()->count(), 50);
        $this->assertCount(0, $this->core->ingest->buffer);
    }

    public function test_it_discards_records_over_the_buffer_threshold_when_not_sampling(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['requests'] = 0;
        $this->core->config['sampling']['exceptions'] = 1.0;

        Route::get('/users', function () {
            for ($i = 0; $i < 1_000; $i++) {
                UserModel::all();
            }

            throw new RuntimeException('Whoops!');
        });

        $response = $this->get('/users');

        $response->assertServerError();
        $ingest->assertWrittenTimes(2);
        $ingest->assertWrite(0, function ($records) {
            $this->assertCount(500, $records);

            return true;
        });
        for ($i = 0; $i < 499; $i++) {
            $ingest->assertWrite(0, "query:{$i}.sql", 'select * from "users"');
        }
        $ingest->assertWrite(0, 'exception:0.message', 'Whoops!');
        $ingest->assertWrite(1, function ($records) {
            $this->assertCount(1, $records);

            return true;
        });
        $ingest->assertWrite(1, 'request:0.url', 'http://localhost/users');
    }

    public function test_it_adds_context_for_job_sampling(): void
    {
        $this->core->config['sampling']['requests'] = 0;
        $this->core->configureSampling('requests');

        $shouldSample = Compatibility::getHiddenContext('nightwatch_should_sample');

        $this->assertFalse($shouldSample);

        $this->core->config['sampling']['requests'] = 1;
        $this->core->configureSampling('requests');

        $shouldSample = Compatibility::getHiddenContext('nightwatch_should_sample');

        $this->assertTrue($shouldSample);
    }

    public function test_dispatched_job_executions_are_not_sampled_if_dispatched_after_exception_when_not_sampling(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['requests'] = 0;
        $this->core->config['sampling']['exceptions'] = 1.0;

        Route::get('/users', function () {
            MyJob::dispatch();

            $this->assertFalse(Compatibility::getHiddenContext('nightwatch_should_sample'));

            app()->terminating(fn () => MyJob::dispatch());

            throw new RuntimeException('Whoops!');
        });

        $response = $this->get('/users');
        $jobs = DB::table('jobs')->get();

        $response->assertServerError();
        $this->assertFalse(Compatibility::getHiddenContext('nightwatch_should_sample'));
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(6, $records);

            return true;
        });
        $ingest->assertLatestWrite('query:0.sql', 'insert into "jobs" ("queue", "attempts", "reserved_at", "available_at", "created_at", "payload") values (?, ?, ?, ?, ?, ?)');
        $ingest->assertLatestWrite('query:1.sql', 'insert into "jobs" ("queue", "attempts", "reserved_at", "available_at", "created_at", "payload") values (?, ?, ?, ?, ?, ?)');
        $ingest->assertLatestWrite('queued-job:0.name', MyJob::class);
        $ingest->assertLatestWrite('queued-job:1.name', MyJob::class);
        $ingest->assertLatestWrite('exception:0.message', 'Whoops!');
        $ingest->assertLatestWrite('request:0.exception_preview', 'Whoops!');
        $this->assertCount(2, $jobs);
        if (Compatibility::$contextExists) {
            $this->assertStringContainsString('"nightwatch_should_sample":"b:0;"', $jobs[0]->payload);
            $this->assertStringContainsString('"nightwatch_should_sample":"b:0;"', $jobs[1]->payload);
            $this->assertStringNotContainsString('"nightwatch_should_sample":"b:1;"', $jobs[0]->payload);
            $this->assertStringNotContainsString('"nightwatch_should_sample":"b:1;"', $jobs[1]->payload);
        } else {
            $this->assertStringContainsString('"nightwatch_should_sample":false', $jobs[0]->payload);
            $this->assertStringContainsString('"nightwatch_should_sample":false', $jobs[1]->payload);
            $this->assertStringNotContainsString('"nightwatch_should_sample":true', $jobs[0]->payload);
            $this->assertStringNotContainsString('"nightwatch_should_sample":true', $jobs[1]->payload);
        }
    }

    public function test_captured_request_gets_exception_preview_after_exception_when_not_sampling(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['requests'] = 0;
        $this->core->config['sampling']['exceptions'] = 1.0;

        Route::get('/users', function () {
            throw new RuntimeException('Whoops!');
        });

        $response = $this->get('/users');

        $response->assertServerError();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(2, $records);

            return true;
        });
        $ingest->assertLatestWrite('exception:0.message', 'Whoops!');
        $ingest->assertLatestWrite('request:0.exception_preview', 'Whoops!');
    }

    public function test_it_can_sample_routes(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', fn () => 'ok');
        Route::get('/users/{user}', fn () => 'ok');
        $this->core->urlBasedSampleRates = [
            '#/users/1#' => 0.0,
            '#/users#' => 1.0,
        ];

        $response = $this->get('/users/1');

        $response->assertOk();
        $ingest->assertWrittenTimes(0);

        $this->app->forgetScopedInstances();

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
    }
}
