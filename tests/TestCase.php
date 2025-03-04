<?php

namespace Tests;

use Illuminate\Config\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Core;
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

        $this->app->make(Repository::class)->set('nightwatch.error_log_channel', 'null');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        Str::createUuidsNormally();
    }

    protected function beforeRefreshingDatabase()
    {
        touch(env('DB_DATABASE'));
    }
}
