<?php

namespace Tests\Unit;

use App\Jobs\MyJob;
use App\Models\User as UserModel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Laravel\Nightwatch\Compatibility;
use Laravel\Nightwatch\Facades\Nightwatch;
use RuntimeException;
use Tests\TestCase;

use function abort;
use function version_compare;

class SamplingTest extends TestCase
{
    protected function setUp(): void
    {
        $this->forceRequestExecutionState();

        parent::setUp();
    }

    public function test_it_can_use_global_config_to_sample_requests(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['requests'] = 0;
        Route::get('/users', fn () => []);

        for ($i = 0; $i < 100; $i++) {
            $this->get('/users')->assertOk();
            $this->app->forgetScopedInstances();
        }

        $ingest->assertWrittenTimes(0);
        $this->assertCount(0, $this->core->ingest->buffer);

        $this->core->config['sampling']['requests'] = 0.25;
        $writes = 0;

        for ($i = 0; $i < 100; $i++) {
            $this->get('/users')->assertOk();
            $this->app->forgetScopedInstances();
            $writes += $ingest->writes()->count();
            $ingest->forgetWrites();
        }

        $this->assertEqualsWithDelta(25, $writes, 20);
        $this->assertCount(0, $this->core->ingest->buffer);

        $this->core->config['sampling']['requests'] = 0.5;
        $writes = 0;

        for ($i = 0; $i < 100; $i++) {
            $this->get('/users')->assertOk();
            $this->app->forgetScopedInstances();
            $writes += $ingest->writes()->count();
            $ingest->forgetWrites();
        }

        $this->assertEqualsWithDelta(50, $writes, 20);
        $this->assertCount(0, $this->core->ingest->buffer);
        $ingest->forgetWrites();

        $this->core->config['sampling']['requests'] = 1.0;
        $writes = 0;

        for ($i = 0; $i < 100; $i++) {
            $this->get('/users')->assertOk();
            $this->app->forgetScopedInstances();
            $writes += $ingest->writes()->count();
            $ingest->forgetWrites();
        }

        $this->assertSame(100, $writes);
        $this->assertCount(0, $this->core->ingest->buffer);
    }

    public function test_it_can_dynamically_set_sample_rate(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['requests'] = 0;
        $callback = null;
        Route::get('/users', function () use (&$callback) {
            $callback();
        });

        $callback = fn () => Nightwatch::dontSample();
        $writes = 0;

        for ($i = 0; $i < 100; $i++) {
            $this->get('/users')->assertOk();
            $this->app->forgetScopedInstances();
            $writes += $ingest->writes()->count();
            $ingest->forgetWrites();
        }

        $this->assertSame(0, $writes);
        $this->assertCount(0, $this->core->ingest->buffer);

        $callback = fn () => Nightwatch::sample(rate: 0);
        $writes = 0;

        for ($i = 0; $i < 100; $i++) {
            $this->get('/users')->assertOk();
            $this->app->forgetScopedInstances();
            $writes += $ingest->writes()->count();
            $ingest->forgetWrites();
        }

        $this->assertSame(0, $writes);
        $this->assertCount(0, $this->core->ingest->buffer);

        $callback = fn () => Nightwatch::sample(rate: 0.25);
        $writes = 0;

        for ($i = 0; $i < 100; $i++) {
            $this->get('/users')->assertOk();
            $this->app->forgetScopedInstances();
            $writes += $ingest->writes()->count();
            $ingest->forgetWrites();
        }

        $this->assertEqualsWithDelta(25, $writes, 20);
        $this->assertCount(0, $this->core->ingest->buffer);
        $ingest->forgetWrites();

        $callback = fn () => Nightwatch::sample(rate: 0.5);
        $writes = 0;

        for ($i = 0; $i < 100; $i++) {
            $this->get('/users')->assertOk();
            $this->app->forgetScopedInstances();
            $writes += $ingest->writes()->count();
            $ingest->forgetWrites();
        }

        $this->assertEqualsWithDelta(50, $writes, 20);
        $this->assertCount(0, $this->core->ingest->buffer);
        $ingest->forgetWrites();

        $callback = fn () => Nightwatch::sample(rate: 1);
        $writes = 0;

        for ($i = 0; $i < 100; $i++) {
            $this->get('/users')->assertOk();
            $this->app->forgetScopedInstances();
            $writes += $ingest->writes()->count();
            $ingest->forgetWrites();
        }

        $this->assertSame(100, $writes);
        $this->assertCount(0, $this->core->ingest->buffer);
        $ingest->forgetWrites();

        $callback = fn () => Nightwatch::sample();
        $writes = 0;

        for ($i = 0; $i < 100; $i++) {
            $this->get('/users')->assertOk();
            $this->app->forgetScopedInstances();
            $writes += $ingest->writes()->count();
            $ingest->forgetWrites();
        }

        $this->assertSame(100, $writes);
        $this->assertCount(0, $this->core->ingest->buffer);
    }

    public function test_it_discards_records_over_the_buffer_threshold_when_not_sampling(): void
    {
        $ingest = $this->fakeIngest();

        Route::get('/users', function () {
            Nightwatch::dontSample();

            for ($i = 0; $i < 555; $i++) {
                UserModel::all();
            }

            $this->assertCount(500, $this->core->ingest->buffer);

            for ($i = 0; $i < 555; $i++) {
                UserModel::all();
            }

            $this->assertCount(500, $this->core->ingest->buffer);
        });

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(0);
        $this->assertCount(0, $this->core->ingest->buffer);
    }

    public function test_it_adds_context_for_job_sampling(): void
    {
        $this->core->dontSample();
        MyJob::dispatch();

        if (Compatibility::$contextExists) {
            $this->assertStringContainsString('"nightwatch_should_sample":"b:0;"', DB::table('jobs')->value('payload'));
        } else {
            $this->assertStringContainsString('"nightwatch_should_sample":false', DB::table('jobs')->value('payload'));
        }
        DB::table('jobs')->truncate();

        $this->core->sample();
        MyJob::dispatch();
        if (Compatibility::$contextExists) {
            $this->assertStringContainsString('"nightwatch_should_sample":"b:1;"', DB::table('jobs')->value('payload'));
        } else {
            $this->assertStringContainsString('"nightwatch_should_sample":true', DB::table('jobs')->value('payload'));
        }
    }

    public function test_it_samples_on_exception(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['requests'] = 0;
        $this->core->sensor->exceptionSensor = fn () => null;
        $exception = new RuntimeException('Whoops!');
        Route::get('/users', fn () => throw $exception);

        $this->core->config['sampling']['exceptions'] = 0;
        $writes = 0;

        for ($i = 0; $i < 100; $i++) {
            $this->get('/users')->assertServerError();
            $this->app->forgetScopedInstances();
            $writes += $ingest->writes()->count();
            $ingest->forgetWrites();
        }

        $this->assertSame(0, $writes);
        $this->assertCount(0, $this->core->ingest->buffer);

        $this->core->config['sampling']['exceptions'] = 0.25;
        $writes = 0;

        for ($i = 0; $i < 100; $i++) {
            $this->get('/users')->assertServerError();
            $this->app->forgetScopedInstances();
            $writes += $ingest->writes()->count();
            $ingest->forgetWrites();
        }

        $this->assertEqualsWithDelta(25, $writes, 20);
        $this->assertCount(0, $this->core->ingest->buffer);
        $ingest->forgetWrites();

        $this->core->config['sampling']['exceptions'] = 0.5;
        $writes = 0;

        for ($i = 0; $i < 100; $i++) {
            $this->get('/users')->assertServerError();
            $this->app->forgetScopedInstances();
            $writes += $ingest->writes()->count();
            $ingest->forgetWrites();
        }

        $this->assertEqualsWithDelta(50, $writes, 20);
        $this->assertCount(0, $this->core->ingest->buffer);
        $ingest->forgetWrites();

        $this->core->config['sampling']['exceptions'] = 1.0;
        $writes = 0;

        for ($i = 0; $i < 100; $i++) {
            $this->get('/users')->assertServerError();
            $this->app->forgetScopedInstances();
            $writes += $ingest->writes()->count();
            $ingest->forgetWrites();
        }

        $this->assertSame(100, $writes);
        $this->assertCount(0, $this->core->ingest->buffer);
    }

    public function test_it_can_sample_unmatched_routes(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['requests'] = 0;
        $callback = null;
        Route::fallback(function () use (&$callback) {
            $callback();

            abort(404);
        });

        $callback = fn () => Nightwatch::dontSample();
        $writes = 0;

        for ($i = 0; $i < 100; $i++) {
            $this->get('/unmatched')->assertNotFound();
            $this->app->forgetScopedInstances();
            $writes += $ingest->writes()->count();
            $ingest->forgetWrites();
        }

        $this->assertSame(0, $writes);
        $this->assertCount(0, $this->core->ingest->buffer);

        $callback = fn () => Nightwatch::sample(rate: 0);
        $writes = 0;

        for ($i = 0; $i < 100; $i++) {
            $this->get('/unmatched')->assertNotFound();
            $this->app->forgetScopedInstances();
            $writes += $ingest->writes()->count();
            $ingest->forgetWrites();
        }

        $this->assertSame(0, $writes);
        $this->assertCount(0, $this->core->ingest->buffer);

        $callback = fn () => Nightwatch::sample(rate: 0.25);
        $writes = 0;

        for ($i = 0; $i < 100; $i++) {
            $this->get('/unmatched')->assertNotFound();
            $this->app->forgetScopedInstances();
            $writes += $ingest->writes()->count();
            $ingest->forgetWrites();
        }

        $this->assertEqualsWithDelta(25, $writes, 20);
        $this->assertCount(0, $this->core->ingest->buffer);
        $ingest->forgetWrites();

        $callback = fn () => Nightwatch::sample(rate: 0.5);
        $writes = 0;

        for ($i = 0; $i < 100; $i++) {
            $this->get('/unmatched')->assertNotFound();
            $this->app->forgetScopedInstances();
            $writes += $ingest->writes()->count();
            $ingest->forgetWrites();
        }

        $this->assertEqualsWithDelta(50, $writes, 20);
        $this->assertCount(0, $this->core->ingest->buffer);
        $ingest->forgetWrites();

        $callback = fn () => Nightwatch::sample(rate: 1);
        $writes = 0;

        for ($i = 0; $i < 100; $i++) {
            $this->get('/unmatched')->assertNotFound();
            $this->app->forgetScopedInstances();
            $writes += $ingest->writes()->count();
            $ingest->forgetWrites();
        }

        $this->assertSame(100, $writes);
        $this->assertCount(0, $this->core->ingest->buffer);
        $ingest->forgetWrites();

        $callback = fn () => Nightwatch::sample();
        $writes = 0;

        for ($i = 0; $i < 100; $i++) {
            $this->get('/unmatched')->assertNotFound();
            $this->app->forgetScopedInstances();
            $writes += $ingest->writes()->count();
            $ingest->forgetWrites();
        }

        $this->assertSame(100, $writes);
        $this->assertCount(0, $this->core->ingest->buffer);
    }

    public function test_it_can_sample_health_checks(): void
    {
        $this->markTestSkippedWhen(version_compare(Application::VERSION, '11.0.0', '<'), 'Health endpoint was released in 11.x');

        $ingest = $this->fakeIngest();
        Event::listen(function (DiagnosingHealth $event) {
            Nightwatch::dontSample();
        });

        $this->get('/up')->assertOk();

        $ingest->assertWrittenTimes(0);
    }
}
