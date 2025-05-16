<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Compatibility;
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

    private Core $core;

    protected function setUp(): void
    {
        parent::setUp();

        $this->core = $this->app->make(Core::class);
        $this->core->flush();
        $this->core->clock->microtimeResolver = fn () => (float) now()->format('U.u');
        Nightwatch::handleUnrecoverableExceptionsUsing(fn ($e) => throw $e);
        Compatibility::$context = [];

        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        Str::createUuidsNormally();

        parent::tearDown();
    }

    protected function beforeRefreshingDatabase(): void
    {
        touch(env('DB_DATABASE'));
    }
}
