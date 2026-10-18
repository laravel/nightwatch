<?php

namespace Tests\Feature;

use Illuminate\Foundation\Application;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Artisan;
use Laravel\Nightwatch\State\RequestState;
use Orchestra\Testbench\Attributes\WithEnv;
use Tests\TestCase;

use function config;
use function gethostname;
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

    public function test_it_does_not_resolve_the_server_name_in_the_config(): void
    {
        $this->assertNull(config('nightwatch.server'));
    }

    public function test_it_falls_back_to_the_hostname_for_the_server_name(): void
    {
        $this->assertSame(gethostname(), $this->core->executionState->server);
    }

    #[WithEnv('NIGHTWATCH_FORCE_REQUEST', '1')]
    public function test_it_falls_back_to_the_hostname_for_the_server_name_during_requests(): void
    {
        $this->assertInstanceOf(RequestState::class, $this->core->executionState);
        $this->assertSame(gethostname(), $this->core->executionState->server);
    }
}
