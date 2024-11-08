<?php

namespace Laravel\Nightwatch;

use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Console\Kernel as ConsoleKernelContract;
use Illuminate\Foundation\Events\Terminating;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Routing\Events\PreparingResponse;
use Illuminate\Routing\Events\ResponsePrepared;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Buffers\PayloadBuffer;
use Laravel\Nightwatch\Console\Agent;
use Laravel\Nightwatch\Contracts\Ingest as IngestContract;
use Laravel\Nightwatch\Contracts\PeakMemoryProvider;
use Laravel\Nightwatch\Ingests\HttpIngest;
use Laravel\Nightwatch\Ingests\NullIngest;
use Laravel\Nightwatch\Ingests\SocketIngest;
use Laravel\Nightwatch\Providers\PeakMemory;
use Laravel\Nightwatch\Records\ExecutionState;
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

use function array_unshift;
use function class_exists;
use function debug_backtrace;
use function defined;
use function microtime;

/**
 * @internal
 */
final class NightwatchServiceProvider extends ServiceProvider
{
    private ?bool $disabled = false;

    public function register(): void
    {
        if ($this->disabled()) {
            return;
        }

        $this->mergeConfig();
        $this->configureAgent();
        $this->configureIngest();
        $this->configureLocation();
        $this->configureMiddleware();
        $this->configureSensorManager();
        $this->configurePeakMemoryProvider();
    }

    public function boot(): void
    {
        if ($this->disabled()) {
            return;
        }

        if ($this->app->runningInConsole()) {
            $this->registerPublications();
            $this->registerCommands();
        }

        $this->registerSensors();
    }

    private function mergeConfig(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nightwatch.php', 'nightwatch');
    }

    private function configureSensorManager(): void
    {
        $this->app->singleton(SensorManager::class);
    }

    private function configureMiddleware(): void
    {
        $this->app->singleton(NightwatchRouteMiddleware::class);

        if (! class_exists(Terminating::class)) {
            $this->app->singleton(NightwatchTerminatingMiddleware::class);
        }
    }

    private function configurePeakMemoryProvider(): void
    {
        $this->app->singleton(PeakMemoryProvider::class, PeakMemory::class);
    }

    private function configureLocation(): void
    {
        $this->app->singleton(Location::class, fn (Application $app) => new Location(
            basePath: $app->basePath(),
            publicPath: $app->publicPath(),
        ));
    }

    /**
     * TODO test if the timeout connector timeout only applies to connection
     * time and not transfer time.
     */
    private function configureAgent(): void
    {
        $this->app->singleton(Agent::class, function (Application $app) {
            /** @var Config */
            $config = $app->make('config');
            /** @var Clock */
            $clock = $app->make(Clock::class);

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

                    return new LimitingServer($server, (int) $config->get('nightwatch.agent.connection_limit'));
                });

            $buffer = new PayloadBuffer($config->get('nightwatch.agent.buffer_threshold'));

            $connector = new Connector([
                'timeout' => $config->get('nightwatch.http.connection_timeout'),
            ], $loop);

            $client = new Client((new Browser($connector, $loop))
                ->withTimeout($config->get('nightwatch.agent.timeout'))
                ->withHeader('User-Agent', 'NightwatchAgent/1')
                ->withHeader('Content-Type', 'application/octet-stream')
                ->withHeader('Content-Encoding', 'gzip')
                ->withHeader('Nightwatch-App-Id', $config->get('nightwatch.app_id'))
                ->withBase('https://khq5ni773stuucqrxebn3a5zbi0ypexu.lambda-url.us-east-1.on.aws/'));

            $ingest = new HttpIngest($client, $clock, $config->get('nightwatch.http.concurrent_request_limit'));
            // $ingest = new NullIngest;

            return new Agent($buffer, $ingest, $loop, $config->get('nightwatch.collector.timeout'));
        });
    }

    private function configureIngest(): void
    {
        $this->app->singleton(IngestContract::class, function (Application $app) {
            /** @var Config */
            $config = $app->make('config');

            $connector = new TimeoutConnector(new TcpConnector, $config->get('nightwatch.collector.connection_timeout'));

            $uri = $config->get('nightwatch.agent.address').':'.$config->get('nightwatch.agent.port');

            return new SocketIngest($connector, $uri);
        });
    }

    private function registerPublications(): void
    {
        $this->publishes([
            __DIR__.'/../config/nightwatch.php' => $this->app->configPath('nightwatch.php'),
        ], ['nightwatch', 'nightwatch-config']);
    }

    private function registerCommands(): void
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
    private function registerSensors(): void
    {
        /** @var Dispatcher */
        $events = $this->app->make('events');

        $clock = $this->app->instance(Clock::class, new Clock(match (true) {
            defined('LARAVEL_START') => LARAVEL_START,
            default => $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true),
        }));

        /** @var ExecutionState */
        $state = $this->app->instance(ExecutionState::class, new ExecutionState(
            trace: $traceId = (string) Str::uuid(),
            id: $traceId,
            context: 'request', // TODO
            deploy: $this->app['config']->get('nightwatch.deploy') ?? '',
            server: $this->app['config']->get('nightwatch.server') ?? '',
            currentExecutionStageStartedAtMicrotime: $clock->executionStartInMicrotime(),
        ));

        /** @var SensorManager */
        $sensor = $this->app->instance(SensorManager::class, new SensorManager($state, $clock, $this->app));

        /*
         * Stage: Before middleware.
         */
        $this->app->booted(fn () => $sensor->stage(ExecutionStage::BeforeMiddleware));

        /*
         * Stage: Action, After middleware, and Terminating.
         */
        $events->listen(RouteMatched::class, function (RouteMatched $event) {
            $middleware = $event->route->action['middleware'] ?? [];

            $middleware[] = NightwatchRouteMiddleware::class; // TODO ensure adding these is not a memory leak in Octane (event though Laravel will make sure they are unique)

            if (! class_exists(Terminating::class)) {
                array_unshift($middleware, NightwatchTerminatingMiddleware::class);
            }

            $event->route->action['middleware'] = $middleware;
        });

        /*
         * Stage: Render.
         */
        $events->listen(PreparingResponse::class, fn () => match ($state->stage) {
            ExecutionStage::Action => $sensor->stage(ExecutionStage::Render),
            default => null,
        });

        /*
         * Stage: After middleware.
         */
        $events->listen(ResponsePrepared::class, fn () => match ($state->stage) {
            ExecutionStage::Render => $sensor->stage(ExecutionStage::AfterMiddleware),
            default => null,
        });

        $events->listen(RequestHandled::class, fn () => $sensor->stage(ExecutionStage::Sending));

        $events->listen(Terminating::class, fn () => $sensor->stage(ExecutionStage::Terminating));

        /*
         * Sensor: Query.
         *
         * The trace will include this listener frame. Instead of trying to
         * slice out the current frame from the trace and having to re-key the
         * array, internally the query sensor will skip the first frame while
         * iterating.
         */
        $events->listen(QueryExecuted::class, fn (QueryExecuted $event) => $sensor->query($event, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)));

        /*
         * Sensor: Exceptions.
         */
        $this->callAfterResolving(ExceptionHandler::class, function (ExceptionHandler $handler) use ($sensor) {
            if (! $handler instanceof Handler) {
                return;
            }

            $handler->reportable($sensor->exception(...));
        });

        /*
         * Sensor: Request + final ingest.
         *
         * TODO we need to determine what to do if the
         * `whenRequestLifecycleIsLongerThan` method is not present. We likely
         * want to hook into the terminating callback system.
         */
        $this->callAfterResolving(HttpKernelContract::class, function (HttpKernelContract $kernel, Application $app) use ($sensor) {
            if (! $kernel instanceof HttpKernel) {
                return;
            }

            if (! class_exists(Terminating::class)) {
                $kernel->setGlobalMiddleware([
                    NightwatchTerminatingMiddleware::class, // Check this isn't a memory leak in Octane
                    ...$kernel->getGlobalMiddleware(),
                ]);
            }

            $kernel->whenRequestLifecycleIsLongerThan(-1, function (Carbon $startedAt, Request $request, Response $response) use ($sensor, $app) {
                $sensor->stage(ExecutionStage::End);

                $sensor->request($request, $response);

                /** @var IngestContract */
                $ingest = $app->make(IngestContract::class);

                $ingest->write($sensor->flush());
            });
        });

        return;

        $events->listen([CacheMissed::class, CacheHit::class], $sensor->cacheEvent(...));
        $events->listen(JobQueued::class, $sensor->queuedJob(...));

        $this->callAfterResolving(Http::class, function (Http $http, Application $app) use ($sensor) {
            /** @var GuzzleMiddleware */
            $middleware = $app->make(GuzzleMiddleware::class, ['sensor' => $sensor]);

            $http->globalMiddleware($middleware);
        });

        $this->callAfterResolving(ConsoleKernelContract::class, function (ConsoleKernelContract $kernel, Application $app) use ($sensor) {
            if (! $kernel instanceof ConsoleKernel) {
                return;
            }

            $kernel->whenCommandLifecycleIsLongerThan(-1, function (Carbon $startedAt, InputInterface $input, int $status) use ($sensor, $app) {
                if (! $this->app->runningInConsole()) {
                    return;
                }

                $sensor->command($startedAt, $input, $status);
                /** @var IngestContract */
                $ingest = $app->make(IngestContract::class);

                $ingest->write($sensor->flush());
            });
        });
    }

    private function disabled(): bool
    {
        return $this->disabled ??= (bool) $this->app->make('config')->get('nightwatch.disabled', false);
    }
}
