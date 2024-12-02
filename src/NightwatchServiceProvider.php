<?php

namespace Laravel\Nightwatch;

use Illuminate\Auth\AuthManager;
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
use Illuminate\Console\Application as Artisan;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Console\Kernel as ConsoleKernelContract;
use Illuminate\Foundation\Events\Terminating;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Routing\Events\PreparingResponse;
use Illuminate\Routing\Events\ResponsePrepared;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Env;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Console\Agent;
use Laravel\Nightwatch\Contracts\LocalIngest;
use Laravel\Nightwatch\Factories\AgentFactory;
use Laravel\Nightwatch\Factories\LocalIngestFactory;
use Laravel\Nightwatch\Hooks\ArtisanStartingHandler;
use Laravel\Nightwatch\Hooks\CacheEventListener;
use Laravel\Nightwatch\Hooks\CommandBootedHandler;
use Laravel\Nightwatch\Hooks\CommandLifecycleIsLongerThanHandler;
use Laravel\Nightwatch\Hooks\ExceptionHandlerResolvedHandler;
use Laravel\Nightwatch\Hooks\HttpClientFactoryResolvedHandler;
use Laravel\Nightwatch\Hooks\HttpKernelResolvedHandler;
use Laravel\Nightwatch\Hooks\JobQueuedListener;
use Laravel\Nightwatch\Hooks\MessageSentListener;
use Laravel\Nightwatch\Hooks\NotificationSentListener;
use Laravel\Nightwatch\Hooks\PreparingResponseListener;
use Laravel\Nightwatch\Hooks\QueryExecutedListener;
use Laravel\Nightwatch\Hooks\RequestBootedHandler;
use Laravel\Nightwatch\Hooks\RequestHandledListener;
use Laravel\Nightwatch\Hooks\ResponsePreparedListener;
use Laravel\Nightwatch\Hooks\RouteMatchedListener;
use Laravel\Nightwatch\Hooks\RouteMiddleware;
use Laravel\Nightwatch\Hooks\TerminatingListener;
use Laravel\Nightwatch\Hooks\TerminatingMiddleware;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Throwable;

use function class_exists;
use function defined;
use function microtime;

/**
 * @internal
 */
final class NightwatchServiceProvider extends ServiceProvider
{
    private float $timestamp;

    /**
     * @var array{
     *     enabled?: bool,
     *     env_id?: string,
     *     env_secret?: string,
     *     deployment?: string,
     *     server?: string,
     *     local_ingest?: string,
     *     remote_ingest?: string,
     *     buffer_threshold?: int,
     *     error_log_channel?: string,
     *     ingests: array{
     *     socket?: array{ uri?: string, connection_limit?: int, connection_timeout?: float, timeout?: float },
     *     http?: array{ uri?: string, connection_limit?: int, connection_timeout?: float, timeout?: float },
     *     log?: array{ channel?: string },
     *     }
     *     }
     */
    private array $nightwatchConfig;

    private Repository $config;

    private bool $isRequest;

    public function register(): void
    {
        // We capture this as early as possible in case the the constant and
        // server variable are not defined.
        $this->timestamp = match (true) {
            defined('LARAVEL_START') => LARAVEL_START,
            default => $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true),
        };

        $this->registerConfig();
        $this->registerBindings();
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->registerPublications();
            $this->registerCommands();
        }

        if (! ($this->nightwatchConfig['enabled'] ?? true)) {
            return;
        }

        $this->determineExecutionType();
        $this->registerHooks();
    }

    private function determineExecutionType(): void
    {
        $this->isRequest = ! $this->app->runningInConsole() || Env::get('NIGHTWATCH_FORCE_REQUEST');
    }

    private function registerConfig(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nightwatch.php', 'nightwatch');

        $this->config = $this->app->make(Repository::class); // @phpstan-ignore assign.propertyType

        $this->nightwatchConfig = $this->config->all()['nightwatch'] ?? []; // @phpstan-ignore method.nonObject
    }

    private function registerBindings(): void
    {
        $this->registerAgent();
        $this->registerLocalIngest();
        $this->registerMiddleware();
    }

    private function registerMiddleware(): void
    {
        $this->app->singleton(RouteMiddleware::class);

        if (! class_exists(Terminating::class)) {
            $this->app->singleton(TerminatingMiddleware::class);
        }
    }

    private function registerAgent(): void
    {
        $this->app->singleton(Agent::class, (new AgentFactory($this->nightwatchConfig))(...));
    }

    private function registerLocalIngest(): void
    {
        $this->app->singleton(LocalIngest::class, (new LocalIngestFactory($this->nightwatchConfig))(...));
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
        $events = $this->app->make(Dispatcher::class);

        /** @var AuthManager */
        $auth = $this->app->make(AuthManager::class);

        /** @var Clock */
        $clock = $this->app->instance(Clock::class, new Clock);

        $state = $this->executionState();

        /** @var Location */
        $location = $this->app->instance(Location::class, new Location(
            $this->app->basePath(), $this->app->publicPath(),
        ));

        $userProvider = new UserProvider($auth);

        /** @var SensorManager */
        $sensor = $this->app->instance(SensorManager::class, new SensorManager(
            $state, $clock, $location, $userProvider, $this->config
        ));

        //
        // -------------------------------------------------------------------------
        // Execution stage hooks
        // --------------------------------------------------------------------------
        //

        if ($this->isRequest) {
            $this->registerRequestHooks($events, $sensor, $state); // @phpstan-ignore argument.type
        } else {
            $this->registerConsoleHooks($events, $sensor, $state); // @phpstan-ignore argument.type
        }

        /**
         * @see \Laravel\Nightwatch\ExecutionStage::Terminating
         */
        $events->listen(Terminating::class, (new TerminatingListener($sensor))(...));

        //
        // -------------------------------------------------------------------------
        // Sensor hooks
        // --------------------------------------------------------------------------
        //

        /**
         * @see \Laravel\Nightwatch\Records\Query
         */
        $events->listen(QueryExecuted::class, (new QueryExecutedListener($sensor))(...));

        /**
         * @see \Laravel\Nightwatch\Records\Exception
         */
        $this->callAfterResolving(ExceptionHandler::class, (new ExceptionHandlerResolvedHandler($sensor))(...));

        /**
         * @see \Laravel\Nightwatch\Records\QueuedJob
         */
        $events->listen(JobQueued::class, (new JobQueuedListener($sensor))(...));

        /**
         * @see \Laravel\Nightwatch\Records\Notification
         */
        $events->listen(NotificationSent::class, (new NotificationSentListener($sensor))(...));

        /**
         * @see \Laravel\Nightwatch\Records\OutgoingRequest
         */
        $this->callAfterResolving(Http::class, (new HttpClientFactoryResolvedHandler($sensor, $clock))(...));

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
        ], (new CacheEventListener($sensor))(...));

        /**
         * @see \Laravel\Nightwatch\Records\Mail
         */
        $events->listen(MessageSent::class, (new MessageSentListener($sensor))(...));
    }

    private function registerRequestHooks(Dispatcher $events, SensorManager $sensor, RequestState $state): void
    {
        /**
         * @see \Laravel\Nightwatch\ExecutionStage::BeforeMiddleware
         */
        $this->app->booted((new RequestBootedHandler($sensor))(...));

        /**
         * @see \Laravel\Nightwatch\ExecutionStage::Terminating
         * @see \Laravel\Nightwatch\ExecutionStage::End
         * @see \Laravel\Nightwatch\Contracts\LocalIngest
         */
        $this->callAfterResolving(HttpKernelContract::class, (new HttpKernelResolvedHandler($sensor, $state))(...));

        /**
         * @see \Laravel\Nightwatch\ExecutionStage::Action
         * @see \Laravel\Nightwatch\ExecutionStage::AfterMiddleware
         * @see \Laravel\Nightwatch\ExecutionStage::Terminating
         */
        $events->listen(RouteMatched::class, (new RouteMatchedListener)(...));

        /**
         * @see \Laravel\Nightwatch\ExecutionStage::Render
         */
        $events->listen(PreparingResponse::class, (new PreparingResponseListener($sensor, $state))(...));

        /**
         * @see \Laravel\Nightwatch\ExecutionStage::AfterMiddleware
         */
        $events->listen(ResponsePrepared::class, (new ResponsePreparedListener($sensor, $state))(...));

        /**
         * @see \Laravel\Nightwatch\ExecutionStage::Sending
         */
        $events->listen(RequestHandled::class, (new RequestHandledListener($sensor))(...));
    }

    private function registerConsoleHooks(Dispatcher $events, SensorManager $sensor, CommandState $state): void
    {
        /**
         * @see \Laravel\Nightwatch\State\CommandState::$artisan
         */
        Artisan::starting((new ArtisanStartingHandler($state))(...));

        /**
         * @see \Laravel\Nightwatch\ExecutionStage::Action
         */
        $this->app->booted((new CommandBootedHandler($sensor))(...));

        // TODO: Set terminating when event doesn't exist

        $events->listen(CommandFinished::class, static function ($event) use ($state) {
            $state->name = $event->command;
        });

        $this->callAfterResolving(ConsoleKernelContract::class, static function (ConsoleKernelContract $kernel, Application $app) use ($sensor, $state) {
            try {
                if (! $kernel instanceof ConsoleKernel) {
                    return;
                }

                $kernel->whenCommandLifecycleIsLongerThan(-1, new CommandLifecycleIsLongerThanHandler($sensor, $state, $app));
            } catch (Throwable $e) {
                //
            }
        });
    }

    private function executionState(): RequestState|CommandState
    {
        if ($this->isRequest) {
            /** @var RequestState */
            $state = $this->app->instance(RequestState::class, new RequestState(
                timestamp: $this->timestamp,
                trace: (string) Str::uuid(),
                currentExecutionStageStartedAtMicrotime: $this->timestamp,
                deploy: $this->nightwatchConfig['deployment'] ?? '',
                server: $this->nightwatchConfig['server'] ?? '',
            ));
        } else {
            /** @var CommandState */
            $state = $this->app->instance(CommandState::class, new CommandState(
                timestamp: $this->timestamp,
                trace: (string) Str::uuid(),
                currentExecutionStageStartedAtMicrotime: $this->timestamp,
                deploy: $this->nightwatchConfig['deployment'] ?? '',
                server: $this->nightwatchConfig['server'] ?? '',
            ));
        }

        return $state;
    }
}
