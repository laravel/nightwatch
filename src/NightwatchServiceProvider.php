<?php

namespace Laravel\Nightwatch;

use Illuminate\Auth\AuthManager;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\ForgettingKey;
use Illuminate\Cache\Events\KeyForgetFailed;
use Illuminate\Cache\Events\KeyForgotten;
use Illuminate\Cache\Events\KeyWriteFailed;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Cache\Events\RetrievingKey;
use Illuminate\Cache\Events\RetrievingManyKeys;
use Illuminate\Cache\Events\WritingKey;
use Illuminate\Cache\Events\WritingManyKeys;
use Illuminate\Console\Events\ArtisanStarting;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Events\Terminating;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\JobQueueing;
use Illuminate\Queue\Queue;
use Illuminate\Routing\Events\PreparingResponse;
use Illuminate\Routing\Events\ResponsePrepared;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\ServiceProvider;
use Laravel\Nightwatch\Console\AgentCommand;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Factories\Logger;
use Laravel\Nightwatch\Hooks\ArtisanStartingListener;
use Laravel\Nightwatch\Hooks\CacheEventListener;
use Laravel\Nightwatch\Hooks\CommandBootedHandler;
use Laravel\Nightwatch\Hooks\CommandStartingListener;
use Laravel\Nightwatch\Hooks\ContextDehydratingHandler;
use Laravel\Nightwatch\Hooks\CreateQueuePayloadHandler;
use Laravel\Nightwatch\Hooks\ExceptionHandlerResolvedHandler;
use Laravel\Nightwatch\Hooks\GlobalMiddleware;
use Laravel\Nightwatch\Hooks\HttpClientFactoryResolvedHandler;
use Laravel\Nightwatch\Hooks\HttpKernelResolvedHandler;
use Laravel\Nightwatch\Hooks\LivewireListener;
use Laravel\Nightwatch\Hooks\LogoutListener;
use Laravel\Nightwatch\Hooks\MailListener;
use Laravel\Nightwatch\Hooks\NotificationListener;
use Laravel\Nightwatch\Hooks\OctaneListener;
use Laravel\Nightwatch\Hooks\PolyfillContextDehydration;
use Laravel\Nightwatch\Hooks\PolyfillContextHydration;
use Laravel\Nightwatch\Hooks\PreparingResponseListener;
use Laravel\Nightwatch\Hooks\QueryExecutedListener;
use Laravel\Nightwatch\Hooks\QueuedJobListener;
use Laravel\Nightwatch\Hooks\RequestBootedHandler;
use Laravel\Nightwatch\Hooks\RequestHandledListener;
use Laravel\Nightwatch\Hooks\ResponsePreparedListener;
use Laravel\Nightwatch\Hooks\RouteMatchedListener;
use Laravel\Nightwatch\Hooks\RouteMiddleware;
use Laravel\Nightwatch\Hooks\TerminatingListener;
use Laravel\Nightwatch\Http\Middleware\Sample;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Laravel\Nightwatch\Support\Uuid;
use Laravel\Octane\Events\RequestReceived;
use Livewire\Livewire;
use Livewire\LivewireManager;
use Ramsey\Uuid\Uuid as BaseUuid;
use Throwable;

use function class_exists;
use function defined;
use function hash;
use function microtime;
use function substr;

/**
 * @internal
 */
final class NightwatchServiceProvider extends ServiceProvider
{
    /**
     * @var Core<RequestState|CommandState>
     */
    private Core $core;

    private float $timestamp;

    private bool $isRequest;

    private Repository $config;

    /**
     * @var array{
     *     enabled?: bool,
     *     sampling?: array{
     *        requests?: float,
     *        commands?: float,
     *        exceptions?: float,
     *        scheduled_tasks?: float,
     *     },
     *     filtering?: array{
     *         ignore_cache_events?: bool,
     *         ignore_mail?: bool,
     *         ignore_notifications?: bool,
     *         ignore_outgoing_requests?: bool,
     *         ignore_queries?: bool,
     *         log_level?: \Psr\Log\LogLevel::*,
     *     },
     *     token?: string,
     *     deployment?: string,
     *     server?: string,
     *     ingest?: array{ uri?: string, timeout?: float|int, connection_timeout?: float|int, event_buffer?: int },
     *     capture_exception_source_code?: bool,
     *     capture_request_payload?: bool,
     *     redact_payload_fields?: string[],
     *     redact_headers?: string[],
     *  }
     */
    private array $nightwatchConfig;

    private ?Throwable $registerException = null;

    public function register(): void
    {
        try {
            $this->captureTimestamp();
            Compatibility::boot($this->app);
            $this->captureExecutionType();
            $this->registerAndCaptureConfig();
            $this->registerBindings();

            if (! $this->core->enabled()) {
                return;
            }

            $this->registerHooks();
        } catch (Throwable $e) {
            $this->registerException = $e;
        }
    }

    public function boot(): void
    {
        try {
            if ($this->registerException) {
                $this->handleAndClearRegisterException();

                return;
            }

            if ($this->app->runningInConsole()) {
                $this->registerPublications();
                $this->registerCommands();
            }
        } catch (Throwable $e) {
            Nightwatch::unrecoverableExceptionOccurred($e);
        }
    }

    private function captureTimestamp(): void
    {
        $this->timestamp = match (true) {
            defined('LARAVEL_START') => LARAVEL_START,
            default => $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true),
        };
    }

    private function captureExecutionType(): void
    {
        $this->isRequest = ! $this->app->runningInConsole() || Env::get('NIGHTWATCH_FORCE_REQUEST');
    }

    private function registerAndCaptureConfig(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nightwatch.php', 'nightwatch');

        $this->config = $this->app->make(Repository::class);

        $this->nightwatchConfig = $this->config->get('nightwatch') ?? []; // @phpstan-ignore assign.propertyType
    }

    private function registerBindings(): void
    {
        $this->registerLogger();
        $this->registerMiddleware();
        $this->registerAgentCommand();
        $this->buildAndRegisterCore();
    }

    private function registerLogger(): void
    {
        if (! $this->config->has('logging.channels.nightwatch')) {
            $this->config->set('logging.channels.nightwatch', [
                'driver' => 'custom',
                'via' => Logger::class,
                'level' => $this->nightwatchConfig['filtering']['log_level'] ?? 'debug',
            ]);
        }

        $this->app->singleton(Logger::class, fn () => new Logger($this->core));
    }

    private function registerMiddleware(): void
    {
        $this->app->singleton(RouteMiddleware::class, fn () => new RouteMiddleware($this->core)); // @phpstan-ignore argument.type

        $this->app->scoped(GlobalMiddleware::class, fn () => new GlobalMiddleware($this->core)); // @phpstan-ignore argument.type

        $this->app->singleton(Sample::class, fn () => new Sample($this->core)); // @phpstan-ignore argument.type
    }

    private function registerAgentCommand(): void
    {
        $this->app->singleton(AgentCommand::class, fn () => new AgentCommand(
            token: $this->nightwatchConfig['token'] ?? null,
            server: $this->nightwatchConfig['server'] ?? null,
            ingestUri: $this->nightwatchConfig['ingest']['uri'] ?? null,
        ));
    }

    private function buildAndRegisterCore(): void
    {
        $clock = new Clock;
        $uuid = new Uuid(static fn () => BaseUuid::uuid4()->toString());
        $executionState = $this->executionState($uuid->make());
        $tokenHash = substr(hash('xxh128', $this->nightwatchConfig['token'] ?? ''), 0, 7);

        $this->app->instance(Core::class, $this->core = new Core(
            ingest: new Ingest(
                transmitTo: $this->nightwatchConfig['ingest']['uri'] ?? '127.0.0.1:2407',
                connectionTimeout: $this->nightwatchConfig['ingest']['connection_timeout'] ?? 0.5,
                timeout: $this->nightwatchConfig['ingest']['timeout'] ?? 0.5,
                streamFactory: new SocketStreamFactory,
                buffer: new RecordsBuffer(
                    length: $this->nightwatchConfig['ingest']['event_buffer'] ?? 500,
                ),
                tokenHash: $tokenHash,
            ),
            sensor: new SensorManager(
                executionState: $executionState,
                clock: $clock = new Clock,
                location: new Location(
                    basePath: $this->app->basePath(),
                    publicPath: $this->app->publicPath(),
                ),
                captureExceptionSourceCode: (bool) ($this->nightwatchConfig['capture_exception_source_code'] ?? true),
                captureRequestPayload: (bool) ($this->nightwatchConfig['capture_request_payload'] ?? false),
                redactPayloadFields: $this->nightwatchConfig['redact_payload_fields'] ?? ['_token', 'password', 'password_confirmation'],
                redactHeaders: $this->nightwatchConfig['redact_headers'] ?? ['Authorization', 'Cookie', 'Proxy-Authorization', 'X-XSRF-TOKEN'],
                config: $this->config,
            ),
            executionState: $executionState,
            clock: $clock,
            uuid: $uuid,
            config: [
                'enabled' => $this->nightwatchConfig['enabled'] ?? true,
                'sampling' => [
                    'requests' => $this->nightwatchConfig['sampling']['requests'] ?? 1.0,
                    'commands' => $this->nightwatchConfig['sampling']['commands'] ?? 1.0,
                    'exceptions' => $this->nightwatchConfig['sampling']['exceptions'] ?? 1.0,
                    'scheduled_tasks' => $this->nightwatchConfig['sampling']['scheduled_tasks'] ?? 1.0,
                ],
                'filtering' => [
                    'ignore_cache_events' => (bool) ($this->nightwatchConfig['filtering']['ignore_cache_events'] ?? false),
                    'ignore_mail' => (bool) ($this->nightwatchConfig['filtering']['ignore_mail'] ?? false),
                    'ignore_notifications' => (bool) ($this->nightwatchConfig['filtering']['ignore_notifications'] ?? false),
                    'ignore_outgoing_requests' => (bool) ($this->nightwatchConfig['filtering']['ignore_outgoing_requests'] ?? false),
                    'ignore_queries' => (bool) ($this->nightwatchConfig['filtering']['ignore_queries'] ?? false),
                ],
            ],
        ));
    }

    private function handleAndClearRegisterException(): void
    {
        Nightwatch::unrecoverableExceptionOccurred($this->registerException); // @phpstan-ignore argument.type

        $this->registerException = null;
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
            Console\AgentCommand::class,
            Console\StatusCommand::class,
        ]);
    }

    private function registerHooks(): void
    {
        $core = $this->core;

        /** @var Dispatcher */
        $events = $this->app->make(Dispatcher::class);

        //
        // -------------------------------------------------------------------------
        // Sensor hooks
        // --------------------------------------------------------------------------
        //

        /**
         * @see \Laravel\Nightwatch\Records\Query
         */
        $events->listen(QueryExecuted::class, (new QueryExecutedListener($core))(...));

        /**
         * @see \Laravel\Nightwatch\Records\Exception
         */
        $this->callAfterResolving(ExceptionHandler::class, (new ExceptionHandlerResolvedHandler($core))(...));

        /**
         * @see \Laravel\Nightwatch\Records\QueuedJob
         */
        $events->listen([JobQueueing::class, JobQueued::class], (new QueuedJobListener($core))(...));

        /**
         * @see \Laravel\Nightwatch\Records\Notification
         */
        $events->listen([NotificationSending::class, NotificationSent::class], (new NotificationListener($core))(...));

        /**
         * @see \Laravel\Nightwatch\Records\Mail
         */
        $events->listen([MessageSending::class, MessageSent::class], (new MailListener($core))(...));

        /**
         * @see \Laravel\Nightwatch\Records\OutgoingRequest
         */
        $this->callAfterResolving(Http::class, (new HttpClientFactoryResolvedHandler($core))(...));

        /**
         * @see \Laravel\Nightwatch\Records\CacheEvent
         */
        $events->listen([
            RetrievingKey::class,
            RetrievingManyKeys::class,
            CacheHit::class,
            CacheMissed::class,
            WritingKey::class,
            WritingManyKeys::class,
            KeyWritten::class,
            KeyWriteFailed::class,
            ForgettingKey::class,
            KeyForgotten::class,
            KeyForgetFailed::class,
        ], (new CacheEventListener($core))(...));

        $events->listen(RequestReceived::class, (new OctaneListener($core))(...)); // @phpstan-ignore class.notFound

        Queue::createPayloadUsing(new CreateQueuePayloadHandler($core));

        if (Compatibility::$contextExists) {
            Context::dehydrating(new ContextDehydratingHandler($core));
        } else {
            Queue::createPayloadUsing(new PolyfillContextDehydration($core));
            $events->listen((new PolyfillContextHydration($core))(...));
        }

        //
        // -------------------------------------------------------------------------
        // Execution stage hooks
        // --------------------------------------------------------------------------
        //

        if ($this->isRequest) {
            /** @var Core<RequestState> $core */
            $this->registerRequestHooks($events, $core);
        } else {
            /** @var Core<CommandState> $core */
            $this->registerConsoleHooks($events, $core);
        }

        /** @var Core<RequestState|CommandState> $core */

        /**
         * @see \Laravel\Nightwatch\ExecutionStage::Terminating
         */
        $events->listen(Terminating::class, (new TerminatingListener($core))(...));
    }

    /**
     * @param  Core<RequestState>  $core
     */
    private function registerRequestHooks(Dispatcher $events, Core $core): void
    {
        // TODO resolve the kernel inline rather than in the listener.

        /**
         * @see \Laravel\Nightwatch\State\RequestState::$user
         *
         * TODO handle this on the queue
         */
        $events->listen(Logout::class, (new LogoutListener($core))(...));

        /**
         * @see \Laravel\Nightwatch\ExecutionStage::BeforeMiddleware
         */
        $this->app->booted((new RequestBootedHandler($core))(...));

        /**
         * @see \Laravel\Nightwatch\ExecutionStage::Action
         * @see \Laravel\Nightwatch\ExecutionStage::Terminating
         */
        $events->listen(RouteMatched::class, (new RouteMatchedListener($core))(...));

        /**
         * @see \Laravel\Nightwatch\ExecutionStage::Render
         */
        $events->listen(PreparingResponse::class, (new PreparingResponseListener($core))(...));

        /**
         * @see \Laravel\Nightwatch\ExecutionStage::AfterMiddleware
         */
        $events->listen(ResponsePrepared::class, (new ResponsePreparedListener($core))(...));

        /**
         * @see \Laravel\Nightwatch\ExecutionStage::Sending
         */
        $events->listen(RequestHandled::class, (new RequestHandledListener($core))(...));

        /**
         * @see \Laravel\Nightwatch\ExecutionStage::End
         * @see \Laravel\Nightwatch\Records\Request
         * @see \Laravel\Nightwatch\ExecutionStage::Terminating
         * @see \Laravel\Nightwatch\Core::finishExecution()
         */
        $this->callAfterResolving(HttpKernelContract::class, (new HttpKernelResolvedHandler($core))(...));

        $this->registerLivewireHooks($core);
    }

    /**
     * @param  Core<CommandState>  $core
     */
    private function registerConsoleHooks(Dispatcher $events, Core $core): void
    {
        /** @var ConsoleKernelContract */
        $kernel = $this->app->make(ConsoleKernelContract::class);

        /**
         * @see \Laravel\Nightwatch\State\CommandState::$artisan
         */
        $events->listen(ArtisanStarting::class, (new ArtisanStartingListener($core))(...));

        /**
         * @see \Laravel\Nightwatch\ExecutionStage::Action
         */
        $this->app->booted((new CommandBootedHandler($core))(...));

        /**
         * @see \Laravel\Nightwatch\State\CommandState::$name
         *
         * Commands...
         * @see \Laravel\Nightwatch\ExecutionStage::Terminating
         * @see \Laravel\Nightwatch\ExecutionStage::End
         * @see \Laravel\Nightwatch\Records\Command
         * @see \Laravel\Nightwatch\Core::finishExecution()
         *
         * Jobs...
         * @see \Laravel\Nightwatch\State\CommandState::$source
         * @see \Laravel\Nightwatch\State\CommandState::flush()
         * @see \Laravel\Nightwatch\State\CommandState::$timestamp
         * @see \Laravel\Nightwatch\State\CommandState::$id
         * @see \Laravel\Nightwatch\Records\JobAttempt
         * @see \Laravel\Nightwatch\Records\Exception
         *
         * Scheduled tasks...
         * @see \Laravel\Nightwatch\Core::finishExecution()
         */
        $events->listen(CommandStarting::class, (new CommandStartingListener($events, $core, $kernel))(...));
    }

    /**
     * @param  Core<RequestState>  $core
     */
    private function registerLivewireHooks(Core $core): void
    {
        if (! class_exists(Livewire::class)) {
            return;
        }

        $this->app->booted(static function ($app) use ($core) {
            if (! $app->bound(LivewireManager::class)) {
                return;
            }

            $listener = new LivewireListener($core);

            // Livewire 2
            Livewire::listen('component.hydrate.subsequent', $listener->componentHydrateSubsequent(...));

            // Livewire 3
            Livewire::listen('hydrate', $listener->hydrate(...));
        });
    }

    private function executionState(string $trace): RequestState|CommandState
    {
        Compatibility::addTraceIdToContext($trace);

        if ($this->isRequest) {
            return new RequestState(
                timestamp: $this->timestamp,
                trace: $trace,
                id: $trace,
                currentExecutionStageStartedAtMicrotime: $this->timestamp,
                deploy: $this->nightwatchConfig['deployment'] ?? '',
                server: $this->nightwatchConfig['server'] ?? '',
                user: $this->userProvider(),
            );
        } else {
            return new CommandState(
                timestamp: $this->timestamp,
                trace: new LazyValue(function () {
                    return (string) Compatibility::getTraceIdFromContext(function () { // @phpstan-ignore cast.string
                        $trace = $this->core->uuid->make();

                        Compatibility::addTraceIdToContext($trace);

                        return $trace;
                    });
                }),
                id: $trace,
                currentExecutionStageStartedAtMicrotime: $this->timestamp,
                deploy: $this->nightwatchConfig['deployment'] ?? '',
                server: $this->nightwatchConfig['server'] ?? '',
                user: $this->userProvider(),
            );
        }
    }

    private function userProvider(): UserProvider
    {
        /** @var AuthManager */
        $auth = $this->app->make(AuthManager::class);

        return new UserProvider(
            fn (callable $callback) => $this->core->ignore(static fn () => $callback($auth)),
            fn () => $this->core->userDetailsResolver,
            fn () => $this->core->report(...),
        );
    }
}
