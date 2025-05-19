<?php

namespace Tests\Feature;

use Illuminate\Foundation\Application;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

use function version_compare;

class ApplicationTest extends TestCase
{
    public function test_it_can_cache_the_config(): void
    {
        $this->markTestSkippedWhen(version_compare(Application::VERSION, '11.0.0', '<'), <<<'MESSAGE'
            Due to Laravel 11's new project structure, we only run this on Laravel 11+.

            The intention of this test is to ensure that we don't put any unseralizable values in the config.

            Running against 11+ should still give a good amount of assurance across other framework versions.
            MESSAGE);
        Env::getRepository()->set('APP_CONFIG_CACHE', $this->app->getCachedConfigPath());

        $result = Artisan::call('config:cache');
        $this->assertSame(0, $result);

        $result = Artisan::call('config:clear');
        $this->assertSame(0, $result);
    }
}
