<?php

namespace Laravel\Nightwatch;

use Closure;
use Exception;
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
use Laravel\Nightwatch\Contracts\LocalIngest;
use Laravel\Nightwatch\Contracts\PeakMemoryProvider;
use Laravel\Nightwatch\Factories\SocketIngestFactory;
use Laravel\Nightwatch\Hooks\BootedHandler;
use Laravel\Nightwatch\Hooks\ExceptionHandlerResolvedHandler;
use Laravel\Nightwatch\Hooks\GuzzleMiddleware;
use Laravel\Nightwatch\Hooks\HttpKernelResolvedHandler;
use Laravel\Nightwatch\Hooks\PreparingResponseListener;
use Laravel\Nightwatch\Hooks\QueryExecutedListener;
use Laravel\Nightwatch\Hooks\RequestHandledListener;
use Laravel\Nightwatch\Hooks\ResponsePreparedListener;
use Laravel\Nightwatch\Hooks\RouteMatchedListener;
use Laravel\Nightwatch\Hooks\RouteMiddleware;
use Laravel\Nightwatch\Hooks\TerminatingListener;
use Laravel\Nightwatch\Hooks\TerminatingMiddleware;
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

use function class_exists;
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
        $this->configureMiddleware();
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

        $this->registerHooks();
    }

    private function mergeConfig(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nightwatch.php', 'nightwatch');
    }

    private function configureMiddleware(): void
    {
        $this->app->singleton(RouteMiddleware::class);

        if (! class_exists(Terminating::class)) {
            $this->app->singleton(TerminatingMiddleware::class);
        }
    }

    private function configurePeakMemoryProvider(): void
    {
        $this->app->singleton(PeakMemoryProvider::class, PeakMemory::class);
    }

    /**
     * TODO test if the timeout connector timeout only applies to connection
     * time and not transfer time.
     */
    private function configureAgent(): void
    {
        $this->app->singleton(Agent::class, static function (Application $app) {
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
                ->give(static function () use ($config, $loop) {
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
        $this->app->singleton(LocalIngest::class, (new SocketIngestFactory)(...));
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
    private function registerHooks(): void
    {
        // TODO what of this can we delay?

        /** @var Dispatcher */
        $events = $this->app->make('events');

        /** @var Clock */
        $clock = $this->app->instance(Clock::class, new Clock(match (true) {
            defined('LARAVEL_START') => LARAVEL_START,
            default => $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true),
        }));

        /** @var ExecutionState */
        $state = $this->app->instance(ExecutionState::class, new ExecutionState(
            trace: $traceId = (string) Str::uuid(),
            id: $traceId,
            context: 'request', // TODO
            currentExecutionStageStartedAtMicrotime: $clock->executionStartInMicrotime(),
            deploy: $this->app['config']->get('nightwatch.deploy') ?? '',
            server: $this->app['config']->get('nightwatch.server') ?? '',
        ));

        /** @var Location */
        $location = $this->app->instance(Location::class, new Location(
            $this->app->basePath(), $this->app->publicPath(),
        ));

        /** @var SensorManager */
        $sensor = $this->app->instance(SensorManager::class, new SensorManager(
            $state, $clock, $location, $this->app,
        ));

        //
        // -------------------------------------------------------------------------
        // Execution stage hooks
        // --------------------------------------------------------------------------
        //

        /**
         * @see \Laravel\Nightwatch\ExecutionStage::BeforeMiddleware
         */
        $this->app->booted(new BootedHandler($sensor));

        /**
         * @see \Laravel\Nightwatch\ExecutionStage::Action
         * @see \Laravel\Nightwatch\ExecutionStage::AfterMiddleware
         * @see \Laravel\Nightwatch\ExecutionStage::Terminating
         */
        $events->listen(RouteMatched::class, new RouteMatchedListener);

        /**
         * @see \Laravel\Nightwatch\ExecutionStage::Render
         */
        $events->listen(PreparingResponse::class, new PreparingResponseListener($sensor, $state));

        /**
         * @see \Laravel\Nightwatch\ExecutionStage::AfterMiddleware
         */
        $events->listen(ResponsePrepared::class, new ResponsePreparedListener($sensor, $state));

        /**
         * @see \Laravel\Nightwatch\ExecutionStage::Sending
         */
        $events->listen(RequestHandled::class, new RequestHandledListener($sensor));

        /**
         * @see \Laravel\Nightwatch\ExecutionStage::Terminating
         */
        $events->listen(Terminating::class, new TerminatingListener($sensor));

        //
        // -------------------------------------------------------------------------
        // Sensor hooks
        // --------------------------------------------------------------------------
        //

        /**
         * @see \Laravel\Nightwatch\Records\Query
         */
        $events->listen(QueryExecuted::class, new QueryExecutedListener($sensor));

        /**
         * @see \Laravel\Nightwatch\Records\Exception
         */
        $this->callAfterResolving(ExceptionHandler::class, (new ExceptionHandlerResolvedHandler($sensor))(...));

        /**
         * @see \Laravel\Nightwatch\ExecutionStage::Terminating
         * @see \Laravel\Nightwatch\ExecutionStage::End
         * @see \Laravel\Nightwatch\Contracts\LocalIngest
         */
        $this->callAfterResolving(HttpKernelContract::class, (new HttpKernelResolvedHandler($sensor))(...));

        return;

        $events->listen([CacheMissed::class, CacheHit::class], static function (CacheMissed|CacheHit $event) use ($sensor) {
            try {
                $sensor->cacheEvent($event);
            } catch (Exception $e) {
                //
            }
        });

        $events->listen(JobQueued::class, static function (JobQueued $event) use ($sensor) {
            try {
                $sensor->queuedJob($sensor);
            } catch (Exception $e) {
                //
            }
        });

        $this->callAfterResolving(Http::class, static function (Http $http) use ($sensor, $clock) {
            try {
                $http->globalMiddleware(new GuzzleMiddleware($sensor, $clock));
            } catch (Exception $e) {
                //
            }
        });

        $this->callAfterResolving(ConsoleKernelContract::class, function (ConsoleKernelContract $kernel, Application $app) use ($sensor) {
            try {
                if (! $kernel instanceof ConsoleKernel) {
                    return;
                }

                $kernel->whenCommandLifecycleIsLongerThan(-1, function (Carbon $startedAt, InputInterface $input, int $status) use ($sensor, $app) {
                    try {
                        if (! $this->app->runningInConsole()) {
                            return;
                        }

                        $sensor->command($startedAt, $input, $status);
                        /** @var LocalIngest */
                        $ingest = $app->make(LocalIngest::class);

                        $ingest->write($sensor->flush());
                    } catch (Exception $e) {
                        //
                    }
                });
            } catch (Exception $e) {
                //
            }
        });
    }

    private function disabled(): bool
    {
        return $this->disabled ??= (bool) $this->app->make('config')->get('nightwatch.disabled', false);
    }
}
