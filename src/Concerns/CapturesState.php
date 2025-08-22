<?php

namespace Laravel\Nightwatch\Concerns;

use Illuminate\Cache\Events\CacheEvent;
use Illuminate\Console\Application as Artisan;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
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
use function memory_reset_peak_usage;
use function random_int;

/**
 * @mixin Core
 */
trait CapturesState
{
    private bool $sampling = true;

    private bool $paused = false;

    private bool $waitingForJob = false;

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

        $this->ingest->shouldDigest($sample);

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
    public function configureGlobalRequestSampling(): void
    {
        $this->sample($this->config['sampling']['requests']);
    }

    /**
     * @internal
     */
    public function configureGlobalCommandSampling(): void
    {
        $this->sample($this->config['sampling']['commands']);
    }

    /**
     * @api
     */
    public function ignore(callable $callback): mixed
    {
        $cachedPaused = $this->paused;

        try {
            $this->paused = true;
            Compatibility::addSamplingToContext(false);

            return $callback();
        } finally {
            $this->paused = $cachedPaused;
            Compatibility::addSamplingToContext(! $this->paused);
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
                $this->ingest->writeNow($this->sensor->fatalError($e));
            } else {
                $this->ingest->write($this->sensor->exception($e, $handled));
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

        if ($this->rejectOutgoingRequestCallback && $this->ignore(fn () => ($this->rejectOutgoingRequestCallback)($record))) {
            return;
        }

        if ($this->redactOutgoingRequestCallback) {
            $this->ignore(fn () => ($this->redactOutgoingRequestCallback)($record));
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

        if ($this->rejectQueryCallback && $this->ignore(fn () => ($this->rejectQueryCallback)($record))) {
            return;
        }

        if ($this->redactQueryCallback) {
            $this->ignore(fn () => ($this->redactQueryCallback)($record));
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

        if ($this->rejectQueuedJobCallback && $this->ignore(fn () => ($this->rejectQueuedJobCallback)($record))) {
            return;
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

        if ($this->rejectNotificationCallback && $this->ignore(fn () => ($this->rejectNotificationCallback)($record))) {
            return;
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

        if ($this->rejectMailCallback && $this->ignore(fn () => ($this->rejectMailCallback)($record))) {
            return;
        }

        if ($this->redactMailCallback) {
            $this->ignore(fn () => ($this->redactMailCallback)($record));
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

        if ($this->rejectCacheEventCallback && $this->ignore(fn () => ($this->rejectCacheEventCallback)($record))) {
            return;
        }

        if ($this->redactCacheEventCallback) {
            $this->ignore(fn () => ($this->redactCacheEventCallback)($record));
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

        if ($this->redactRequestCallback) {
            $this->ignore(fn () => ($this->redactRequestCallback)($record));
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
    public function waitForJob(): void
    {
        $this->waitingForJob = true;
    }

    /**
     * @internal
     */
    public function configureForJobs(): void
    {
        $this->executionState->source = 'job';
        $this->waitingForJob = true;
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
            Compatibility::getSamplingFromContext(default: true) ? 1.0 : 0.0
        );

        $this->waitingForJob = false;
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

        if ($this->redactCommandCallback) {
            $this->ignore(fn () => ($this->redactCommandCallback)($record));
        }

        $this->ingest->write($resolver());
    }

    /**
     * @internal
     */
    public function configureForScheduledTasks(): void
    {
        $this->executionState->source = 'schedule';
    }

    /**
     * @internal
     */
    public function prepareForNextScheduledTask(): void
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
