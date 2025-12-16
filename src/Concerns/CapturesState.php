<?php

namespace Laravel\Nightwatch\Concerns;

use Illuminate\Cache\Events\CacheEvent;
use Illuminate\Console\Application as Artisan;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\JobQueueing;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Illuminate\Routing\Route;
use Laravel\Nightwatch\Compatibility;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Hooks\GlobalMiddleware;
use Laravel\Nightwatch\Hooks\RouteMiddleware;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Laravel\Nightwatch\Types\Str;
use Monolog\LogRecord;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\ErrorHandler\Error\FatalError;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use WeakMap;

use function array_shift;
use function array_unshift;
use function debug_backtrace;
use function env;
use function in_array;
use function memory_reset_peak_usage;
use function preg_match;
use function preg_split;
use function random_int;
use function str_replace;
use function trim;

/**
 * @internal
 *
 * @mixin Core
 */
trait CapturesState
{
    private bool $sampling = true;

    private bool $paused = false;

    private bool $captureDefaultVendorCommands = false;

    /**
     * @var WeakMap<Event, float>
     */
    private WeakMap $scheduledTasksSampleRates;

    /**
     * @var WeakMap<Route, bool>
     */
    private WeakMap $routesWithMiddlewareRegistered;

    /**
     * @api
     */
    public function sample(float $rate = 1.0): void
    {
        if ($rate < 0 || $rate > 1) {
            $rate = 0.0;
        }

        $sample = (random_int(0, PHP_INT_MAX) / PHP_INT_MAX) <= $rate;

        $this->sampling = $sample;

        $this->ingest->shouldDigestWhenBufferIsFull($sample);

        Compatibility::addSamplingToContext($sample);
    }

    /**
     * @api
     */
    public function dontSample(): void
    {
        $this->sample(rate: 0);
    }

    /**
     * @api
     */
    public function sampling(): bool
    {
        return $this->sampling;
    }

    /**
     * @internal
     */
    public function configureRequestSampling(): void
    {
        $this->sample($this->config['sampling']['requests']);
    }

    /**
     * @internal
     */
    public function configureCommandSampling(string $command): void
    {
        if (! $this->captureDefaultVendorCommands && in_array($command, $this->defaultVendorCommands(), true)) {
            $this->dontSample();

            return;
        }

        $this->sample(match (Compatibility::getSamplingFromContext(null)) {
            true => 1.0,
            false => 0.0,
            null => $this->config['sampling']['commands'],
        });
    }

    /**
     * @internal
     */
    public function configureScheduledTaskSampling(Event $event): void
    {
        if (! $this->captureDefaultVendorCommands) {
            $command = str_replace(
                [Artisan::phpBinary(), Artisan::artisanBinary()],
                '',
                $event->command ?? ''
            );

            $command = preg_split('/\s+/', trim($command), 2)[0] ?? '';

            if (in_array($command, $this->defaultVendorCommands(), true)) {
                $this->dontSample();

                return;
            }
        }

        $this->sample(rate: $this->scheduledTasksSampleRates[$event] ?? $this->config['sampling']['scheduled_tasks']);
    }

    /**
     * @api
     */
    public function captureDefaultVendorCommands(bool $capture = true): void
    {
        $this->captureDefaultVendorCommands = $capture;
    }

    /**
     * @api
     *
     * @return list<string>
     */
    public static function defaultVendorCommands(): array
    {
        return [
            'auth:clear-resets',
            'config:cache',
            'horizon:snapshot',
            'horizon:status',
            'horizon:supervisor',
            'inertia:start-ssr',
            'invoke-serialized-closure',
            'model:prune',
            'nightwatch:agent',
            'nightwatch:status',
            'queue:monitor',
            'reverb:start',
            'schedule:list',
        ];
    }

    /**
     * @api
     */
    public function ignore(callable $callback): mixed
    {
        $cachedPaused = $this->paused;
        $cachedSamplingInContext = Compatibility::getSamplingFromContext();

        try {
            $this->paused = true;
            Compatibility::addSamplingToContext(false);

            return $callback();
        } finally {
            $this->paused = $cachedPaused;
            Compatibility::addSamplingToContext($cachedSamplingInContext);
        }
    }

    /**
     * @api
     */
    public function resume(): void
    {
        $this->paused = false;

        Compatibility::addSamplingToContext(true);
    }

    /**
     * @api
     */
    public function pause(): void
    {
        $this->paused = true;

        Compatibility::addSamplingToContext(false);
    }

    /**
     * @api
     */
    public function paused(): bool
    {
        return $this->paused;
    }

    /**
     * @api
     */
    public function report(Throwable $e, ?bool $handled = null): void
    {
        if (! $this->enabled()) {
            return;
        }

        if (! $this->sampling) {
            $this->sample($this->config['sampling']['exceptions']);
        }

        try {
            if ($e instanceof FatalError) {
                if ($this->sampling) {
                    $this->ingest->writeNow($this->sensor->fatalError($e));
                }
            } else {
                [$record, $resolver] = $this->sensor->exception($e, $handled);

                foreach ($this->redactExceptionCallbacks as $callback) {
                    $this->ignore(static fn () => ($callback)($record));
                }

                $this->ingest->write($resolver());
            }
        } catch (Throwable $e) {
            Nightwatch::unrecoverableExceptionOccurred($e);
        }
    }

    /**
     * @internal
     */
    public function log(LogRecord $log): void
    {
        $this->ingest->write($this->sensor->log($log));
    }

    /**
     * @internal
     */
    public function outgoingRequest(float $startMicrotime, float $endMicrotime, RequestInterface $request, ResponseInterface $response): void
    {
        [$record, $resolver] = $this->sensor->outgoingRequest($startMicrotime, $endMicrotime, $request, $response);

        foreach ($this->rejectOutgoingRequestCallbacks as $callback) {
            if ($this->ignore(static fn () => ($callback)($record))) {
                return;
            }
        }

        foreach ($this->redactOutgoingRequestCallbacks as $callback) {
            $this->ignore(static fn () => ($callback)($record));
        }

        $this->ingest->write($resolver());
    }

    /**
     * @internal
     */
    public function query(QueryExecuted $event): void
    {
        if ($this->config['filtering']['ignore_queries'] || $this->paused) {
            return;
        }

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, limit: 21);
        array_shift($trace);

        [$record, $resolver] = $this->sensor->query($event, $trace);

        foreach ($this->rejectQueryCallbacks as $callback) {
            if ($this->ignore(static fn () => ($callback)($record))) {
                return;
            }
        }

        foreach ($this->redactQueryCallbacks as $callback) {
            $this->ignore(static fn () => ($callback)($record));
        }

        $this->ingest->write($resolver());
    }

    /**
     * @internal
     */
    public function queuedJob(JobQueueing|JobQueued $event): void
    {
        if ($this->paused) {
            return;
        }

        $queuedJob = $this->sensor->queuedJob($event);

        if ($queuedJob === null) {
            return;
        }

        [$record, $resolver] = $queuedJob;

        foreach ($this->rejectQueuedJobCallbacks as $callback) {
            if ($this->ignore(static fn () => ($callback)($record))) {
                return;
            }
        }

        $this->ingest->write($resolver());
    }

    /**
     * @internal
     */
    public function notification(NotificationSending|NotificationSent $event): void
    {
        if ($this->config['filtering']['ignore_notifications'] || $this->paused) {
            return;
        }

        $notification = $this->sensor->notification($event);

        if ($notification === null) {
            return;
        }

        [$record, $resolver] = $notification;

        foreach ($this->rejectNotificationCallbacks as $callback) {
            if ($this->ignore(static fn () => ($callback)($record))) {
                return;
            }
        }

        $this->ingest->write($resolver());
    }

    /**
     * @internal
     */
    public function mail(MessageSending|MessageSent $event): void
    {
        if ($this->config['filtering']['ignore_mail'] || $this->paused) {
            return;
        }

        $mail = $this->sensor->mail($event);

        if ($mail === null) {
            return;
        }

        [$record, $resolver] = $mail;

        foreach ($this->rejectMailCallbacks as $callback) {
            if ($this->ignore(static fn () => ($callback)($record))) {
                return;
            }
        }

        foreach ($this->redactMailCallbacks as $callback) {
            $this->ignore(static fn () => ($callback)($record));
        }

        $this->ingest->write($resolver());
    }

    /**
     * @internal
     */
    public function cacheEvent(CacheEvent $event): void
    {
        if ($this->config['filtering']['ignore_cache_events'] || $this->paused) {
            return;
        }

        $cacheEvent = $this->sensor->cacheEvent($event);

        if ($cacheEvent === null) {
            return;
        }

        [$record, $resolver] = $cacheEvent;

        $rejectKeys = $this->captureDefaultVendorCacheKeys
            ? $this->rejectCacheKeys
            : [...$this->defaultVendorCacheKeys(), ...$this->rejectCacheKeys];

        foreach ($rejectKeys as $reject) {
            $match = @preg_match($reject, $record->key);

            if ($match === 1) {
                return;
            }

            if ($match === false && $record->key === $reject) {
                return;
            }
        }

        foreach ($this->rejectCacheEventCallbacks as $callback) {
            if ($this->ignore(static fn () => ($callback)($record))) {
                return;
            }
        }

        foreach ($this->redactCacheEventCallbacks as $callback) {
            $this->ignore(static fn () => ($callback)($record));
        }

        $this->ingest->write($resolver());
    }

    /**
     * @internal
     */
    public function stage(ExecutionStage $stage): void
    {
        if ($this->executionStageIs($stage)) {
            return;
        }

        $this->sensor->stage($stage);
    }

    /**
     * @internal
     */
    public function executionStageIs(ExecutionStage $stage): bool
    {
        return $this->executionState->stage === $stage;
    }

    /**
     * @internal
     */
    public function remember(Authenticatable $user): void
    {
        $this->executionState->user->remember($user);
    }

    /**
     * @internal
     */
    public function captureUser(): void
    {
        $user = $this->sensor->user();

        if ($user !== null) {
            $this->ingest->write($user);
        }
    }

    /**
     * @internal
     */
    public function request(Request $request, Response $response): void
    {
        [$record, $resolver] = $this->sensor->request($request, $response);

        foreach ($this->redactRequestCallbacks as $callback) {
            $this->ignore(static fn () => ($callback)($record));
        }

        $this->ingest->write($resolver());
    }

    /**
     * @internal
     */
    public function jobAttempt(JobProcessed|JobReleasedAfterException|JobFailed $event): void
    {
        $jobAttempt = $jobAttempt = $this->sensor->jobAttempt($event);

        if ($jobAttempt !== null) {
            $this->ingest->write($jobAttempt);
        }
    }

    /**
     * @internal
     */
    public function captureRequestPreview(Request $request): void
    {
        $this->executionState->executionPreview = Str::tinyText(
            $request->getMethod().' '.$request->getBaseUrl().$request->getPathInfo()
        );
    }

    /**
     * @internal
     */
    public function captureRequestRouteAction(string $routeAction): void
    {
        /** @var Core<RequestState> $this */
        if ($this->executionState->routeAction === null) {
            $this->executionState->routeAction = $routeAction;
        } else {
            $this->executionState->routeAction .= ', '.$routeAction;
        }
    }

    /**
     * @internal
     */
    public function attachMiddlewareToRoute(Route $route): void
    {
        if ($this->routesWithMiddlewareRegistered[$route] ?? false) {
            return;
        }

        /** @var array<string> */
        $middleware = $route->middleware();

        /**
         * @see \Laravel\Nightwatch\ExecutionStage::Action
         */
        $middleware[] = RouteMiddleware::class;

        if (! Compatibility::$terminatingEventExists) {
            /**
             * @see \Laravel\Nightwatch\ExecutionStage::Terminating
             */
            array_unshift($middleware, GlobalMiddleware::class);
        }

        $route->action['middleware'] = $middleware;

        $this->routesWithMiddlewareRegistered[$route] = true;
    }

    /**
     * @internal
     */
    public function waitForExecution(): void
    {
        $this->dontSample();
    }

    /**
     * @internal
     */
    public function configureForJobs(): void
    {
        $this->executionState->source = 'job';
        $this->waitForExecution();
    }

    /**
     * @internal
     */
    public function prepareForNextJob(): void
    {
        $this->flush();
        $this->resume();
        memory_reset_peak_usage();
    }

    /**
     * @internal
     */
    public function prepareForJob(Job $job): void
    {
        /** @var Core<CommandState> $this */
        if ($this->isVapor()) {
            $this->prepareForNextJob();
        }

        $this->sample(
            Compatibility::getSamplingFromContext() ? 1.0 : 0.0
        );

        $this->executionState->timestamp = $this->clock->microtime();
        $this->executionState->setId($this->uuid->make());
        $this->executionState->executionPreview = Str::tinyText($job->resolveName());

        // Beanstalkd throws an exception when attempting to retrieve the job
        // after it has been processed. Previously, we were retrieving the attempts
        // when listening for the `JobProcessed|JobReleasedAfterException|JobFailed`
        // events, however the job has already been removed from beanstalkd
        // when these events fire. Instead, we will capture it much earlier in
        // the lifecycle to ensure we can always retrieve the value.
        $this->executionState->attempts = $job->attempts();
    }

    /**
     * @internal
     */
    public function captureArtisan(Artisan $artisan): void
    {
        /** @var Core<CommandState> $this */
        $this->executionState->artisan = $artisan;
    }

    /**
     * @internal
     */
    public function prepareForCommand(string $name): void
    {
        /** @var Core<CommandState> $this */
        $this->executionState->name = $name;
        $this->executionState->executionPreview = Str::tinyText($name);
    }

    /**
     * @internal
     */
    public function capturingCommandNamed(string $name): bool
    {
        /** @var Core<CommandState> $this */
        return $this->executionState->name === $name;
    }

    /**
     * @internal
     */
    public function command(InputInterface $input, int $status): void
    {
        [$record, $resolver] = $this->sensor->command($input, $status);

        foreach ($this->redactCommandCallbacks as $callback) {
            $this->ignore(static fn () => ($callback)($record));
        }

        $this->ingest->write($resolver());
    }

    /**
     * @internal
     */
    public function configureForScheduledTasks(): void
    {
        $this->executionState->source = 'schedule';
        $this->waitForExecution();
    }

    /**
     * @internal
     */
    public function prepareForNextScheduledTask(Event $event): void
    {
        /*
         * Reset state for the current scheduled task execution.
         * Since `schedule:run` executes multiple tasks sequentially,
         * we need to clear previous task data to avoid metric pollution.
         */
        $this->flush();
        $this->resume();
        memory_reset_peak_usage();

        $trace = $this->uuid->make();
        Compatibility::addTraceIdToContext($trace);
        $this->executionState->trace = $trace;
        $this->executionState->setId($trace);
        $this->executionState->timestamp = $this->clock->microtime();
        $this->configureScheduledTaskSampling($event);
    }

    /**
     * @internal
     */
    public function scheduledTask(ScheduledTaskFinished|ScheduledTaskSkipped|ScheduledTaskFailed $event): void
    {
        $scheduledTask = $this->sensor->scheduledTask($event);

        if ($scheduledTask !== null) {
            $this->ingest->write($scheduledTask);
        }
    }

    /**
     * @internal
     */
    public function prepareForNextRequest(): void
    {
        /** @var Core<RequestState> $this */
        $this->flush();
        $this->resume();
        memory_reset_peak_usage();

        $timestamp = $this->clock->microtime();
        $this->executionState->stage = ExecutionStage::BeforeMiddleware;
        $this->executionState->timestamp = $timestamp;
        $this->executionState->currentExecutionStageStartedAtMicrotime = $timestamp;

        $trace = $this->uuid->make();
        $this->executionState->trace = $trace;
        $this->executionState->setId($trace);
        Compatibility::addTraceIdToContext($trace);
    }

    /**
     * @internal
     */
    public function shouldCaptureLogs(): bool
    {
        return $this->enabled();
    }

    /**
     * @internal
     */
    public function sampleScheduledTask(Event $event, float $rate): void
    {
        $this->scheduledTasksSampleRates[$event] = $rate;
    }

    /**
     * @internal
     */
    public function flush(): void
    {
        $this->executionState->flush();
        $this->ingest->flush();
    }

    private function isVapor(): bool
    {
        return env('VAPOR_SSM_PATH') !== null;
    }
}
