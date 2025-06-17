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
use Laravel\Nightwatch\Types\Str;
use Monolog\LogRecord;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use WeakMap;

use function array_shift;
use function array_unshift;
use function debug_backtrace;
use function memory_reset_peak_usage;
use function random_int;

/**
 * @mixin Core
 */
trait CapturesState
{
    /**
     * @internal
     */
    public bool $shouldSample = true;

    /**
     * @internal
     */
    public bool $shouldSampleOnException = true;

    private bool $waitingForJob = false;

    /**
     * @var WeakMap<Route, bool>
     */
    private WeakMap $routesWithMiddlewareRegistered;

    /**
     * @internal
     *
     * @param  'requests'|'commands'  $by
     */
    public function configureSampling(string $by): void
    {
        $sampleFloat = random_int(0, PHP_INT_MAX) / PHP_INT_MAX;

        $this->ingest->shouldDigest(
            $this->shouldSample = $sampleFloat <= $this->config['sampling'][$by]
        );

        $this->shouldSampleOnException = $sampleFloat <= $this->config['sampling']['exceptions'];

        Compatibility::addHiddenContext('nightwatch_should_sample', $this->shouldSample);

        if (! $this->potentiallySampling()) {
            $this->flush();
        }
    }

    /**
     * @api
     */
    public function report(Throwable $e, ?bool $handled = null): void
    {
        if (! $this->enabled()) {
            return;
        }

        if ($this->shouldSampleOnException) {
            $this->ingest->shouldDigest(
                $this->shouldSample = true
            );
        }

        if (! $this->shouldSample) {
            return;
        }

        try {
            $this->sensor->exception($e, $handled);
        } catch (Throwable $e) {
            Nightwatch::unrecoverableExceptionOccurred($e);
        }
    }

    /**
     * @internal
     */
    public function log(LogRecord $log): void
    {
        $this->sensor->log($log);
    }

    /**
     * @internal
     */
    public function outgoingRequest(float $startMicrotime, float $endMicrotime, RequestInterface $request, ResponseInterface $response): void
    {
        $this->sensor->outgoingRequest($startMicrotime, $endMicrotime, $request, $response);
    }

    /**
     * @internal
     */
    public function query(QueryExecuted $event): void
    {
        if (! $this->potentiallySampling()) {
            return;
        }

        if ($this->config['filtering']['ignore_queries']) {
            return;
        }

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, limit: 21);
        array_shift($trace);

        $this->sensor->query($event, $trace);
    }

    /**
     * @internal
     */
    public function queuedJob(JobQueueing|JobQueued $event): void
    {
        if (! $this->potentiallySampling()) {
            return;
        }

        $this->sensor->queuedJob($event);
    }

    /**
     * @internal
     */
    public function notification(NotificationSending|NotificationSent $event): void
    {
        if (! $this->potentiallySampling()) {
            return;
        }

        if ($this->config['filtering']['ignore_notifications']) {
            return;
        }

        $this->sensor->notification($event);
    }

    /**
     * @internal
     */
    public function mail(MessageSending|MessageSent $event): void
    {
        if (! $this->potentiallySampling()) {
            return;
        }

        if ($this->config['filtering']['ignore_mail']) {
            return;
        }

        $this->sensor->mail($event);
    }

    /**
     * @internal
     */
    public function cacheEvent(CacheEvent $event): void
    {
        if (! $this->potentiallySampling()) {
            return;
        }

        if ($this->config['filtering']['ignore_cache_events']) {
            return;
        }

        $this->sensor->cacheEvent($event);
    }

    /**
     * @internal
     */
    public function stage(ExecutionStage $stage): void
    {
        if (! $this->potentiallySampling()) {
            return;
        }

        if ($this->executionStageIs($stage)) {
            throw new RuntimeException("Cannot transition to the same stage [{$stage->value}].");
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
        if (! $this->potentiallySampling()) {
            return;
        }

        $this->executionState->user->remember($user);
    }

    /**
     * @internal
     */
    public function captureUser(): void
    {
        if (! $this->shouldSample) {
            return;
        }

        $this->sensor->user();
    }

    /**
     * @internal
     */
    public function request(Request $request, Response $response): void
    {
        if (! $this->shouldSample) {
            return;
        }

        $this->sensor->request($request, $response);
    }

    /**
     * @internal
     */
    public function jobAttempt(JobProcessed|JobReleasedAfterException|JobFailed $event): void
    {
        if (! $this->potentiallySampling()) {
            return;
        }

        $this->sensor->jobAttempt($event);
    }

    /**
     * @internal
     */
    public function captureRequestPreview(Request $request): void
    {
        if (! $this->potentiallySampling()) {
            return;
        }

        $this->executionState->executionPreview = Str::tinyText(
            $request->getMethod().' '.$request->getBaseUrl().$request->getPathInfo()
        );
    }

    /**
     * @internal
     */
    public function attachMiddlewareToRoute(Route $route): void
    {
        if (! $this->potentiallySampling()) {
            return;
        }

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
        memory_reset_peak_usage();
    }

    /**
     * @internal
     */
    public function prepareForJob(Job $job): void
    {
        $this->ingest->shouldDigest(
            $this->shouldSample = (bool) Compatibility::getHiddenContext('nightwatch_should_sample', true)
        );

        $this->shouldSampleOnException = (random_int(0, PHP_INT_MAX) / PHP_INT_MAX) <= $this->config['sampling']['exceptions'];

        if (! $this->potentiallySampling()) {
            return;
        }

        $this->waitingForJob = false;
        $this->executionState->timestamp = $this->clock->microtime();
        $this->executionState->setId((string) Str::uuid());
        $this->executionState->executionPreview = Str::tinyText($job->resolveName());
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
        if (! $this->potentiallySampling()) {
            return;
        }

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
        if (! $this->shouldSample) {
            return;
        }

        $this->sensor->command($input, $status);
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
        memory_reset_peak_usage();

        $trace = (string) Str::uuid();
        Compatibility::addHiddenContext('nightwatch_trace_id', $trace);
        $this->executionState->trace = $trace;
        $this->executionState->setId($trace);
        $this->executionState->timestamp = $this->clock->microtime();
    }

    /**
     * @internal
     */
    public function scheduledTask(ScheduledTaskFinished|ScheduledTaskSkipped|ScheduledTaskFailed $event): void
    {
        $this->sensor->scheduledTask($event);
    }

    /**
     * @internal
     */
    public function potentiallySampling(): bool
    {
        return $this->shouldSample || $this->shouldSampleOnException;
    }

    /**
     * @internal
     */
    public function shouldCaptureLogs(): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        return $this->potentiallySampling();
    }

    /**
     * @internal
     */
    public function flush(): void
    {
        $this->executionState->flush();
        $this->ingest->flush();
    }
}
