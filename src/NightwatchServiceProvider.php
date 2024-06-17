<?php

namespace Laravel\Nightwatch;

use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Console\Kernel as ConsoleKernelContract;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Buffers\PayloadBuffer;
use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\Console\Agent;
use Laravel\Nightwatch\Contracts\Clock as ClockContract;
use Laravel\Nightwatch\Contracts\Ingest as IngestContract;
use Laravel\Nightwatch\Contracts\PeakMemoryProvider;
use Laravel\Nightwatch\Ingests\HttpIngest;
use Laravel\Nightwatch\Ingests\NullIngest;
use Laravel\Nightwatch\Ingests\SocketIngest;
use Laravel\Nightwatch\Providers\PeakMemory;
use React\EventLoop\StreamSelectLoop;
use React\Http\Browser;
use React\Socket\Connector;
use React\Socket\LimitingServer;
use React\Socket\ServerInterface;
use React\Socket\TcpConnector;
use React\Socket\TcpServer;
use React\Socket\TimeoutConnector;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\HttpFoundation\Response;

final class NightwatchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if ($this->app['config']->get('app.nightwatch_disabled')) {
            return;
        }

        $this->app->singleton(SensorManager::class);
        $this->app->singleton(ClockContract::class, function (Container $app) {
            /**
             * TODO this needs to better handle Laravel Octane and the queue worker.
             */
            return new Clock(match (true) {
                defined('LARAVEL_START') => LARAVEL_START,
                ($start = $app->make('request')->server('REQUEST_TIME_FLOAT')) => $start,
                default => microtime(true),
            });
        });
        $this->app->singleton(PeakMemoryProvider::class, PeakMemory::class);
        $this->app->scoped(RecordsBuffer::class);
        $this->configureAgent();
        $this->configureIngest();
        $this->configureTraceId();
        $this->mergeConfig();
    }

    public function boot(): void
    {
        if ($this->app['config']->get('app.nightwatch_disabled')) {
            return;
        }

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

    /**
     * TODO test if the timeout connector timeout only applies to connection
     * time and not transfer time.
     */
    protected function configureAgent(): void
    {
        $this->app->singleton(Agent::class, function (Container $app) {
            /** @var Config */
            $config = $app->make(Config::class);
            /** @var Clock */
            $clock = $app->make(ClockContract::class);

            $loop = new StreamSelectLoop;

            // Creating an instance of the `TcpServer` will automatically start
            // the server.  To ensure do not start the server when the command
            // is constructed, but only when called, we make sure to resolve
            // the server in the handle method instead.
            $app->when([Agent::class, 'handle'])
                ->needs(ServerInterface::class)
                ->give(function () use ($config, $loop) {
                    $uri = $config->get('nightwatch.agent.address').':'.$config->get('nightwatch.agent.port');

                    $server = new TcpServer($uri, $loop);

                    return new LimitingServer($server, $config->get('nightwatch.agent.connection_limit'));
                });

            $buffer = new PayloadBuffer($config->get('nightwatch.agent.buffer_threshold'));

            $connector = new Connector([
                'timeout' => $config->get('nightwatch.http.connection_timeout'),
            ], $loop);

            $client = new Client((new Browser($connector, $loop))
                ->withTimeout($config->get('nightwatch.agent.timeout'))
                ->withHeader('User-Agent', 'NightwatchAgent/1.0.0')
                ->withHeader('Content-Type', 'application/octet-stream')
                ->withHeader('Content-Encoding', 'gzip')
                ->withHeader('Nightwatch-App-Id', $config->get('nightwatch.app_id'))
                ->withBase('https://5qdb6aj5xtgmwvytfyjb2kfmhi0gpiya.lambda-url.us-east-1.on.aws'));

            // $ingest = new HttpIngest($client, $clock, $config->get('nightwatch.http.concurrent_request_limit'));
            $ingest = new NullIngest;

            return new Agent($buffer, $ingest, $loop, $config->get('nightwatch.collector.timeout'));
        });
    }

    protected function configureIngest(): void
    {
        $this->app->singleton(IngestContract::class, function (Container $app) {
            /** @var Config */
            $config = $app->make(Config::class);

            $connector = new TimeoutConnector(new TcpConnector, $config->get('nightwatch.collector.connection_timeout'));

            $uri = $config->get('nightwatch.agent.address').':'.$config->get('nightwatch.agent.port');

            return new SocketIngest($connector, $uri);
        });
    }

    /**
     * TODO on the queue we need to restore the trace ID from the request / command.
     */
    protected function configureTraceId(): void
    {
        $this->app->scoped('laravel.nightwatch.trace_id', fn () => (string) Str::uuid());
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

    /**
     * TODO Alternative approach to storing when not using Laravel's HTTP and
     * console kernel. Also for custom exception handlers.
     * TODO We had special ordering in Pulse to ensure our
     * recorders were registered early but out ingest was registered last. This
     * we used the `booted` callback.
     */
    protected function registerSensors(): void
    {
        /** @var SensorManager */
        $sensor = $this->app->make(SensorManager::class);
        /** @var Dispatcher */
        $events = $this->app->make(Dispatcher::class);

        $events->listen(QueryExecuted::class, $sensor->query(...));
        $events->listen([CacheMissed::class, CacheHit::class], $sensor->cacheEvent(...));
        $events->listen(JobQueued::class, $sensor->queuedJob(...));

        $this->callAfterResolving(Http::class, function (Http $http, Container $app) use ($sensor) {
            /** @var GuzzleMiddleware */
            $middleware = $app->make(GuzzleMiddleware::class, ['sensor' => $sensor]);

            $http->globalMiddleware($middleware);
        });

        $this->callAfterResolving(ExceptionHandler::class, function (ExceptionHandler $handler) use ($sensor) {
            if (! $handler instanceof Handler) {
                return;
            }

            $handler->reportable($sensor->exception(...));
        });

        $this->callAfterResolving(HttpKernelContract::class, function (HttpKernelContract $kernel, Container $app) use ($sensor) {
            if (! $kernel instanceof HttpKernel) {
                return;
            }

            $kernel->whenRequestLifecycleIsLongerThan(-1, function (Carbon $startedAt, Request $request, Response $response) use ($sensor, $app) {
                $sensor->request($startedAt, $request, $response);

                /** @var IngestContract */
                $ingest = $app->make(IngestContract::class);

                // $ingest->write($sensor->flush());
                $sensor->flush();
            });
        });

        $this->callAfterResolving(ConsoleKernelContract::class, function (ConsoleKernelContract $kernel, Container $app) use ($sensor) {
            if (! $kernel instanceof ConsoleKernel) {
                return;
            }

            $kernel->whenCommandLifecycleIsLongerThan(-1, function (Carbon $startedAt, InputInterface $input, int $status) use ($sensor, $app) {
                $sensor->command($startedAt, $input, $status);

                /** @var IngestContract */
                $ingest = $app->make(IngestContract::class);

                $ingest->write($sensor->flush());
            });
        });
    }
}
