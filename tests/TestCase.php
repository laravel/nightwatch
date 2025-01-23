<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

use function env;
use function nightwatch;
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
        nightwatch()->state->reset();
    }
}
