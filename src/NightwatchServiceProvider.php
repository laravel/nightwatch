<?php

namespace Laravel\Nightwatch;

use DateTimeInterface;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Console\Agent;
use Laravel\Nightwatch\Contracts\Client as ClientContract;
use Laravel\Nightwatch\Contracts\Ingest as IngestContract;
use Laravel\Nightwatch\Contracts\PeakMemoryProvider;
use Laravel\Nightwatch\Providers\PeakMemory;
use Laravel\Nightwatch\Sensors\Sensor;
use React\EventLoop\StreamSelectLoop;
use React\Http\Browser;
use React\Socket\Connector;
use React\Socket\LimitingServer;
use React\Socket\ServerInterface;
use React\Socket\TcpConnector;
use React\Socket\TcpServer;
use React\Socket\TimeoutConnector;
use Symfony\Component\HttpFoundation\Response;

final class NightwatchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Sensor::class, function (Container $app) {
            /** @var Config */
            $config = $app->make(Config::class);
            /** @var RecordCollection */
            $records = $app->make(RecordCollection::class);

            /** @var array{ server: string|false, deploy_id: string|null } */
            $nightwatchConfig = $config->get('nightwatch');

            return new Sensor($app);
        });
        $this->app->singleton(PeakMemoryProvider::class, PeakMemory::class);
        $this->app->scoped(RecordCollection::class);
        $this->configureAgent();
        $this->configureClient();
        $this->configureIngest();
        $this->configureTraceId();
        $this->mergeConfig();
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->registerPublications();
            $this->registerCommands();
        }

        $this->registerSensors();
    }

    protected function mergeConfig(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nightwatch.php', 'nightwatch');
    }

    protected function configureAgent(): void
    {
        $this->app->singleton(Agent::class, function (Container $app) {
            /** @var Config */
            $config = $app->make(Config::class);

            $loop = new StreamSelectLoop;

            $buffer = new RecordBuffer($config->get('nightwatch.agent.buffer_threshold'));

            $app->when([Agent::class, 'handle'])
                ->needs(ServerInterface::class)
                ->give(function () use ($config, $loop) {
                    $uri = $config->get('nightwatch.agent.address').':'.$config->get('nightwatch.agent.port');

                    return new LimitingServer(
                        new TcpServer($uri, $loop),
                        $config->get('nightwatch.agent.connection_limit')
                    );
                });

            $connector = new Connector([
                'timeout' => $config->get('nightwatch.http.connection_timeout'), // TODO: test if this is the connection only or total duration.
            ], $loop);

            $ingest = new Ingest($app->make(ClientContract::class, [
                'loop' => $loop,
                'connector' => $connector,
            ]), $config->get('nightwatch.http.concurrent_request_limit'));

            return new Agent($buffer, $ingest, $loop, $config->get('nightwatch.collector.timeout'));
        });
    }

    protected function configureClient(): void
    {
        $this->app->singleton(ClientContract::class, function (Container $app, array $args) {
            /** @var Config */
            $config = $app->make(Config::class);

            $args = $args + ['connector' => null, 'loop' => null];

            return new Client((new Browser($args['connector'], $args['loop']))
                ->withTimeout($config->get('nightwatch.agent.timeout'))
                ->withHeader('User-Agent', 'NightwatchAgent/1.0.0') // TODO use actual version instead of 1.0.0
                ->withHeader('Content-Type', 'application/json') // TODO: gzip...
                // ->withHeader('Content-Type', 'application/octet-stream')
                // ->withHeader('Content-Encoding', 'gzip')
                ->withHeader('Nightwatch-App-Id', $config->get('nightwatch.app_id'))
                ->withHeader('Authorization', "Bearer {$config->get('nightwatch.app_secret')}")
                ->withBase("https://5qdb6aj5xtgmwvytfyjb2kfmhi0gpiya.lambda-url.{$config->get('nightwatch.http.region')}.on.aws"));
        });
    }

    protected function registerPublications(): void
    {
        $this->publishes([
            __DIR__.'/../config/nightwatch.php' => $this->app->configPath('nightwatch.php'),
        ], ['nightwatch', 'nightwatch-config']);
    }

    protected function registerCommands(): void
    {
        $this->commands([
            Console\Agent::class,
        ]);
    }

    protected function registerSensors(): void
    {
        /** @var Dispatcher */
        $events = $this->app->make(Dispatcher::class);
        /** @var Sensor */
        $sensor = $this->app->make(Sensor::class);

        $events->listen(QueryExecuted::class, $sensor->queries(...));
        $events->listen([CacheMissed::class, CacheHit::class], $sensor->cacheEvents(...));
        $this->callAfterResolving(Http::class, fn (Http $http) => $http->globalMiddleware(new GuzzleMiddleware($sensor)));

        $this->callAfterResolving(HttpKernel::class, function (HttpKernel $kernel, Container $app) use ($sensor) {
            if (! method_exists($kernel, 'whenRequestLifecycleIsLongerThan')) {
                // TODO implement an alternative approach
                return;
            }

            $kernel->whenRequestLifecycleIsLongerThan(-1, function (DateTimeInterface $startedAt, Request $request, Response $response) use ($sensor, $app) {
                $sensor->requests($startedAt, $request, $response);

                /** @var IngestContract */
                $ingest = $app->make(IngestContract::class);
                /** @var RecordCollection */
                $records = $app->make(RecordCollection::class);

                $ingest->write($records->forget('execution_parent')->toJson());
            });
        });
    }

    protected function configureIngest(): void
    {
        $this->app->singleton(IngestContract::class, function (Container $app) {
            /** @var Config */
            $config = $app->make(Config::class);

            $connector = new TimeoutConnector(new TcpConnector, $config->get('nightwatch.collector.connection_timeout'));

            $uri = $config->get('nightwatch.agent.address').':'.$config->get('nightwatch.agent.port');

            return new TcpIngest($connector, $uri);
        });
    }

    protected function configureTraceId(): void
    {
        // TODO: on the queue we need to restore the trace ID from the request / command.
        // TODO make the UUID lazy, so that it isn't calculated until it is resolved? or will it
        // *always* be used if it is resolved? I don't think so. We will resolve it when
        // create a listener, but the listener may not be triggered. Also, we should
        // probably not use the `Str` helper here so we have full control over the
        // UUID generated and it isn't impacted by user modifications.
        $this->app->scoped('nightwatch.trace_id', fn () => Str::uuid()->toString());
    }
}
