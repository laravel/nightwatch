<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\Facades\Nightwatch;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use RuntimeException;

use function env;
use function now;
use function touch;

abstract class TestCase extends OrchestraTestCase
{
    use RefreshDatabase, WithWorkbench;

    private Core $core;

    protected function setUp(): void
    {
        parent::setUp();

        $this->core = $this->app->make(Core::class);
        $this->core->state->reset();
        $this->core->clock->microtimeResolver = fn () => (float) now()->format('U.u');
        Nightwatch::handleUnrecoverableExceptionsUsing(fn ($e) => throw $e);

        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        if (($count = $this->core->state->exceptions) > 0) {
            throw new RuntimeException("{$count} exception(s) were captured that you did not forget. Remember to call `forgetRecordedExceptions(\$count)` at the end of your test after asserting against the expected exception count.");
        }

        Str::createUuidsNormally();

        parent::tearDown();
    }

    protected function beforeRefreshingDatabase(): void
    {
        touch(env('DB_DATABASE'));
    }
}
