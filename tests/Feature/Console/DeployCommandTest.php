<?php

namespace Tests\Feature\Console;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\Attributes\WithEnv;
use Tests\TestCase;

use function now;

class DeployCommandTest extends TestCase
{
    #[WithEnv('NIGHTWATCH_TOKEN', 'test-token')]
    #[WithEnv('NIGHTWATCH_DEPLOY', 'v1.2.3')]
    public function test_it_can_run_the_deploy_command(): void
    {
        $this->freezeTime();
        Http::fake([
            '*/api/deployments' => function (Request $request) {
                $this->assertEquals(['Bearer test-token'], $request->header('Authorization'));
                $this->assertEquals([
                    'timestamp' => now()->getTimestamp(),
                    'version' => 'v1.2.3',
                ], $request->data());

                return Http::response('OK');
            },
        ]);

        $this->artisan('nightwatch:deploy')
            ->expectsOutput('Deployment successful')
            ->assertExitCode(0);
    }

    #[WithEnv('NIGHTWATCH_TOKEN', 'test-token')]
    public function test_it_can_run_the_deploy_command_without_a_version(): void
    {
        $this->freezeTime();
        Http::fake([
            '*/api/deployments' => function (Request $request) {
                $this->assertEquals(['Bearer test-token'], $request->header('Authorization'));
                $this->assertEquals([
                    'timestamp' => now()->getTimestamp(),
                    'version' => '',
                ], $request->data());

                return Http::response('OK');
            },
        ]);

        $this->artisan('nightwatch:deploy')
            ->expectsOutput('Deployment successful')
            ->assertExitCode(0);
    }

    #[WithEnv('NIGHTWATCH_TOKEN', '')]
    public function test_it_fails_when_the_deploy_command_is_run_without_a_token(): void
    {
        $this->artisan('nightwatch:deploy')
            ->expectsOutput('No NIGHTWATCH_TOKEN environment variable configured.')
            ->assertExitCode(1);
    }

    #[WithEnv('NIGHTWATCH_TOKEN', 'test-token')]
    public function test_it_handles_http_errors(): void
    {
        Http::fake([
            '*/api/deployments' => Http::response('Whoops!', 500),
        ]);

        $this->artisan('nightwatch:deploy')
            ->expectsOutput('Deployment failed: 500 [Whoops!]')
            ->assertExitCode(1);
    }

    #[WithEnv('NIGHTWATCH_TOKEN', 'test-token')]
    public function test_it_handles_connection_errors(): void
    {
        Http::fake([
            '*/api/deployments' => fn () => throw new ConnectionException('Whoops!'),
        ]);

        $this->artisan('nightwatch:deploy')
            ->expectsOutput('Deployment failed: [Whoops!]')
            ->assertExitCode(1);
    }
}
