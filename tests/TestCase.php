<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\Facades\Nightwatch;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

use function env;
use function now;
use function touch;

abstract class TestCase extends OrchestraTestCase
{
    use RefreshDatabase, WithWorkbench;

    protected function setUp(): void
    {
        parent::setUp();

        $core = $this->app->make(Core::class);
        $core->state->reset();
        $core->clock->microtimeResolver = fn () => (float) now()->format('U.u');

        Nightwatch::handleUnrecoverableExceptionsUsing(fn ($e) => throw $e);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        Str::createUuidsNormally();
    }

    protected function beforeRefreshingDatabase(): void
    {
        touch(env('DB_DATABASE'));
    }
}
