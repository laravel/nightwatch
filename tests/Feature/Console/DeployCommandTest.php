<?php

namespace Tests\Feature\Console;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Nightwatch\Console\DeployCommand;
use Orchestra\Testbench\Attributes\WithEnv;
use Tests\TestCase;

use function env;
use function json_encode;
use function now;

class DeployCommandTest extends TestCase
{
    #[WithEnv('NIGHTWATCH_TOKEN', 'test-token')]
    #[WithEnv('NIGHTWATCH_DEPLOY', 'v1.2.3')]
    public function test_it_can_run_the_deploy_command_with_no_arguments_when_env_var_is_set(): void
    {
        $this->freezeTime();
        Http::fake([
            '*/api/deployments' => function (Request $request) {
                $this->assertEquals(['Bearer '.env('NIGHTWATCH_TOKEN')], $request->header('Authorization'));
                $this->assertEquals([
                    'timestamp' => now()->toDateTimeString('microsecond'),
                    'ref' => 'v1.2.3',
                    'name' => null,
                    'url' => '',
                ], $request->data());

                return Http::response('OK');
            },
        ]);

        $this->artisan('nightwatch:deploy')
            ->expectsOutputToContain('Deployment sent to Nightwatch successfully.')
            ->assertExitCode(0);
    }

    #[WithEnv('NIGHTWATCH_TOKEN', 'test-token')]
    public function test_it_accepts_arguments_and_options(): void
    {
        Http::fake([
            '*/api/deployments' => function (Request $request) {
                $this->assertEquals(['Bearer '.env('NIGHTWATCH_TOKEN')], $request->header('Authorization'));
                $this->assertEquals([
                    'timestamp' => '2025-12-22 15:30:45.123456',
                    'ref' => 'v1.2.3',
                    'name' => 'Happy Friday!',
                    'url' => 'https://example.com/deployments/123',
                ], $request->data());

                return Http::response('OK');
            },
        ]);

        $this->artisan('nightwatch:deploy v1.2.3 --timestamp="2025-12-22 15:30:45.123456" --name="Happy Friday!" --url="https://example.com/deployments/123"')
            ->expectsOutputToContain('Deployment sent to Nightwatch successfully.')
            ->assertExitCode(0);
    }

    #[WithEnv('NIGHTWATCH_DEPLOY', 'v1.2.3')]
    public function test_it_fails_when_the_deploy_command_is_run_without_a_token(): void
    {
        $this->app->singleton(DeployCommand::class, fn () => new DeployCommand(token: null));

        $this->artisan('nightwatch:deploy')
            ->expectsOutputToContain('Please configure the [NIGHTWATCH_TOKEN] environment variable.')
            ->assertExitCode(0);
    }

    #[WithEnv('NIGHTWATCH_TOKEN', 'test-token')]
    #[WithEnv('NIGHTWATCH_DEPLOY', 'v1.2.3')]
    public function test_it_handles_error_responses(): void
    {
        Http::fake([
            '*/api/deployments' => Http::response(json_encode(['message' => 'Invalid environment token.']), 403),
        ]);

        $this->artisan('nightwatch:deploy')
            ->expectsOutputToContain('Deployment could not be sent to Nightwatch: Invalid environment token.')
            ->assertExitCode(0);
    }

    #[WithEnv('NIGHTWATCH_TOKEN', 'test-token')]
    #[WithEnv('NIGHTWATCH_DEPLOY', 'v1.2.3')]
    public function test_it_handles_http_errors(): void
    {
        Http::fake([
            '*/api/deployments' => Http::response('Whoops!', 500),
        ]);

        $this->artisan('nightwatch:deploy')
            ->expectsOutputToContain('Deployment could not be sent to Nightwatch: [500] Whoops!')
            ->assertExitCode(0);
    }

    #[WithEnv('NIGHTWATCH_TOKEN', 'test-token')]
    #[WithEnv('NIGHTWATCH_DEPLOY', 'v1.2.3')]
    public function test_it_handles_connection_errors(): void
    {
        Http::fake([
            '*/api/deployments' => fn () => throw new ConnectionException('Connection timeout.'),
        ]);

        $this->artisan('nightwatch:deploy')
            ->expectsOutputToContain('Deployment could not be sent to Nightwatch: Connection timeout.')
            ->assertExitCode(0);
    }
}
