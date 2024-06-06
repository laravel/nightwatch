<?php

namespace Laravel\Nightwatch;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use Laravel\Nightwatch\Console\Agent;
use Laravel\Nightwatch\Contracts\Client as ClientContract;
use Laravel\Nightwatch\Sensors\RequestSensor;
use React\EventLoop\StreamSelectLoop;
use React\Http\Browser;
use React\Socket\Connector;
use React\Socket\LimitingServer;
use React\Socket\TcpServer;

final class NightwatchServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string>
     */
    public array $singletons = [
        RequestSensor::class,
    ];

    public function register(): void
    {
        $this->app->bind(ClientContract::class, fn (): ClientContract => new Client((new Browser($connector, $loop))
            ->withTimeout($config->get('nightwatch.agent.timeout'))
            ->withHeader('User-Agent', 'NightwatchAgent/1.0.0') // TODO use actual version instead of 1.0.0
            ->withHeader('Content-Type', 'application/json') // TODO: gzip...
            // ->withHeader('Content-Type', 'application/octet-stream')
            // ->withHeader('Content-Encoding', 'gzip')
            ->withHeader('Nightwatch-App-Id', $config->get('nightwatch.app_id'))
            ->withHeader('Authorization', "Bearer {$config->get('nightwatch.app_secret')}")
            ->withBase("https://5qdb6aj5xtgmwvytfyjb2kfmhi0gpiya.lambda-url.{$config->get('nightwatch.http.region')}.on.aws")));

        $this->app->bind(Agent::class, function (Container $app): Agent {
            /** @var Config */
            $config = $app->make(Config::class);

            $loop = new StreamSelectLoop;

            $buffer = new RecordBuffer($config->get('nightwatch.agent.buffer_threshold'));

            $uri = $config->get('nightwatch.agent.address').':'.$config->get('nightwatch.agent.port');

            $server = new LimitingServer(
                new TcpServer($uri, $loop),
                $config->get('nightwatch.agent.connection_limit')
            );

            $connector = new Connector([
                'timeout' => $config->get('nightwatch.http.connection_timeout'), // TODO: test if this is the connection only or total duration.
            ], $loop);

            $ingest = new Ingest($app->make(ClientContract::class), $config->get('nightwatch.http.concurrent_request_limit'));

            return new Agent($buffer, $ingest, $server, $loop, $config->get('nightwatch.collector.timeout'));
        });

        $this->callAfterResolving(Kernel::class, function (Kernel $kernel, Container $app) {
            if (method_exists($kernel, 'whenRequestLifecycleIsLongerThan')) {
                $kernel->whenRequestLifecycleIsLongerThan(-1, $app->make(RequestSensor::class));
            }
        });

        $this->mergeConfigFrom(
            __DIR__.'/../config/nightwatch.php', 'nightwatch'
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/nightwatch.php' => config_path('nightwatch.php'),
            ], ['nightwatch', 'nightwatch-config']);

            $this->commands([
                Console\Agent::class,
            ]);
        }
    }
}
