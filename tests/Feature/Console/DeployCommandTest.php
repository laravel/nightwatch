<?php

namespace Tests\Feature\Console;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Laravel\Nightwatch\Console\DeployCommand;
use Laravel\Nightwatch\NightwatchDeployException;
use Orchestra\Testbench\Attributes\WithEnv;
use Tests\TestCase;
use Throwable;

use function env;
use function json_encode;
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
                $this->assertEquals(['Bearer '.env('NIGHTWATCH_TOKEN')], $request->header('Authorization'));
                $this->assertEquals([
                    'v' => 1,
                    'timestamp' => now()->getTimestamp(),
                    'version' => 'v1.2.3',
                ], $request->data());

                return Http::response('OK');
            },
        ]);

        $this->artisan('nightwatch:deploy')
            ->expectsOutputToContain('Deployment sent to Nightwatch successfully.')
            ->assertExitCode(0);
    }

    #[WithEnv('NIGHTWATCH_TOKEN', 'test-token')]
    public function test_it_can_run_the_deploy_command_without_a_version(): void
    {
        $this->freezeTime();
        Http::fake([
            '*/api/deployments' => function (Request $request) {
                $this->assertEquals(['Bearer '.env('NIGHTWATCH_TOKEN')], $request->header('Authorization'));
                $this->assertEquals([
                    'v' => 1,
                    'timestamp' => now()->getTimestamp(),
                    'version' => '',
                ], $request->data());

                return Http::response('OK');
            },
        ]);

        $this->artisan('nightwatch:deploy')
            ->expectsOutputToContain('Deployment sent to Nightwatch successfully.')
            ->assertExitCode(0);
    }

    public function test_it_fails_when_the_deploy_command_is_run_without_a_token(): void
    {
        $reported = null;
        $this->app->make(ExceptionHandler::class)->reportable(function (Throwable $e) use (&$reported) {
            $reported = $e;
        });
        $this->app->singleton(DeployCommand::class, fn () => new DeployCommand(token: null));

        $this->artisan('nightwatch:deploy')
            ->expectsOutputToContain('Please configure the [NIGHTWATCH_TOKEN] environment variable.')
            ->assertExitCode(0);

        $this->assertInstanceOf(NightwatchDeployException::class, $reported);
        $this->assertSame('NIGHTWATCH_TOKEN environment variable is not configured.', $reported?->getMessage());
    }

    #[WithEnv('NIGHTWATCH_TOKEN', 'test-token')]
    public function test_it_handles_error_responses(): void
    {
        $reported = null;
        $this->app->make(ExceptionHandler::class)->reportable(function (Throwable $e) use (&$reported) {
            $reported = $e;
        });
        Http::fake([
            '*/api/deployments' => Http::response(json_encode(['message' => 'Invalid environment token.']), 403),
        ]);

        $this->artisan('nightwatch:deploy')
            ->expectsOutputToContain('Deployment could not be sent to Nightwatch: Invalid environment token.')
            ->assertExitCode(0);

        $this->assertInstanceOf(NightwatchDeployException::class, $reported);
        $this->assertSame('Invalid environment token.', $reported?->getMessage());
        $this->assertInstanceOf(RequestException::class, $reported?->getPrevious());
    }

    #[WithEnv('NIGHTWATCH_TOKEN', 'test-token')]
    public function test_it_handles_http_errors(): void
    {
        $reported = null;
        $this->app->make(ExceptionHandler::class)->reportable(function (Throwable $e) use (&$reported) {
            $reported = $e;
        });
        Http::fake([
            '*/api/deployments' => Http::response('Whoops!', 500),
        ]);

        $this->artisan('nightwatch:deploy')
            ->expectsOutputToContain('Deployment could not be sent to Nightwatch: [500] Whoops!')
            ->assertExitCode(0);

        $this->assertInstanceOf(NightwatchDeployException::class, $reported);
        $this->assertSame('[500] Whoops!', $reported?->getMessage());
        $this->assertInstanceOf(RequestException::class, $reported?->getPrevious());
    }

    #[WithEnv('NIGHTWATCH_TOKEN', 'test-token')]
    public function test_it_handles_connection_errors(): void
    {
        $reported = null;
        $this->app->make(ExceptionHandler::class)->reportable(function (Throwable $e) use (&$reported) {
            $reported = $e;
        });
        Http::fake([
            '*/api/deployments' => fn () => throw new ConnectionException('Connection timeout.'),
        ]);

        $this->artisan('nightwatch:deploy')
            ->expectsOutputToContain('Deployment could not be sent to Nightwatch: Connection timeout.')
            ->assertExitCode(0);

        $this->assertInstanceOf(NightwatchDeployException::class, $reported);
        $this->assertSame('Connection timeout.', $reported?->getMessage());
        $this->assertInstanceOf(ConnectionException::class, $reported?->getPrevious());
    }
}
