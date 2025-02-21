<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Nightwatch\Core;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

use function app;
use function env;
use function touch;

abstract class TestCase extends OrchestraTestCase
{
    use RefreshDatabase, WithWorkbench;

    protected function beforeRefreshingDatabase()
    {
        touch(env('DB_DATABASE'));
    }

    protected function afterRefreshingDatabase()
    {
        app(Core::class)->state->reset();
    }
}
