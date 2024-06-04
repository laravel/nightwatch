<?php

namespace Tests;

use Illuminate\Support\Facades\Artisan;
use Laravel\Nightwatch\LaravelPackage;
use Laravel\Nightwatch\NightwatchServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app)
    {
        return [NightwatchServiceProvider::class];
    }
}
