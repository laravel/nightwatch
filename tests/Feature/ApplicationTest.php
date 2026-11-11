<?php

namespace Tests\Feature;

use Illuminate\Support\Env;
use Illuminate\Support\Facades\Artisan;
use Laravel\Nightwatch\State\RequestState;
use Orchestra\Testbench\Attributes\WithEnv;
use Tests\TestCase;

use function config;
use function gethostname;

class ApplicationTest extends TestCase
{
    public function test_it_can_cache_the_config(): void
    {
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
