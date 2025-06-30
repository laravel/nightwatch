<?php

use Illuminate\Support\Facades\Route;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Http\Middleware\Sample;
use Tests\TestCase;

class SampleTest extends TestCase
{
    protected function setUp(): void
    {
        $this->forceRequestExecutionState();

        parent::setUp();
    }

    public function test_it_can_sample_specific_routes_via_middleware()
    {
        $ingest = $this->fakeIngest();

        Route::get('/users-0', fn () => [])
            ->middleware(Sample::rate(0));
        $writes = 0;

        for ($i = 0; $i < 100; $i++) {
            $this->get('/users-0')->assertOk();
            $this->app->forgetScopedInstances();
            $writes += $ingest->writes()->count();
            $ingest->forgetWrites();
        }

        $this->assertSame(0, $writes);

        Route::get('/users-50', fn () => [])
            ->middleware(Sample::rate(0.5));
        $writes = 0;

        for ($i = 0; $i < 100; $i++) {
            $this->get('/users-50')->assertOk();
            $this->app->forgetScopedInstances();
            $writes += $ingest->writes()->count();
            $ingest->forgetWrites();
        }

        $this->assertEqualsWithDelta(50, $writes, 20);

        Route::get('/users-100', fn () => [])
            ->middleware(Sample::rate(1));
        $writes = 0;

        for ($i = 0; $i < 100; $i++) {
            $this->get('/users-100')->assertOk();
            $this->app->forgetScopedInstances();
            $writes += $ingest->writes()->count();
            $ingest->forgetWrites();
        }

        $this->assertSame(100, $writes);
    }

    public function test_it_can_sample_unmatched_routes()
    {
        $ingest = $this->fakeIngest();
        Route::fallback(fn () => abort(404))->middleware(Sample::rate(0));

        $writes = 0;

        for ($i = 0; $i < 100; $i++) {
            $this->get('/wp-admin.php')->assertNotFound();
            $this->app->forgetScopedInstances();
            $writes += $ingest->writes()->count();
            $ingest->forgetWrites();
        }

        $this->assertSame(0, $writes);

        Route::fallback(fn () => abort(404))->middleware(Sample::rate(0.5));
        $writes = 0;

        for ($i = 0; $i < 100; $i++) {
            $this->get('/wp-admin.php')->assertNotFound();
            $this->app->forgetScopedInstances();
            $writes += $ingest->writes()->count();
            $ingest->forgetWrites();
        }

        $this->assertEqualsWithDelta(50, $writes, 20);

        Route::fallback(fn () => abort(404))->middleware(Sample::rate(1));
        $writes = 0;

        for ($i = 0; $i < 100; $i++) {
            $this->get('/wp-admin.php')->assertNotFound();
            $this->app->forgetScopedInstances();
            $writes += $ingest->writes()->count();
            $ingest->forgetWrites();
        }

        $this->assertSame(100, $writes);
    }

    public function test_it_has_priority(): void
    {
        $this->app->instance('throwing-middleware', function ($request, $next) {
            if (Nightwatch::sampling()) {
                throw new RuntimeException('Whoops!');
            }

            return $next($request);
        });

        $response = $this->get('/sampled-or-throw');

        $response->assertOk();
    }
}
