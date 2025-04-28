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
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\JobQueueing;
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
        $this->shouldSample = (random_int(0, PHP_INT_MAX) / PHP_INT_MAX) <= $this->sampling[$by];

        Compatibility::addHiddenContext('nightwatch_should_sample', $this->shouldSample);

        if (! $this->shouldSample) {
            $this->state->records->flush();
        }
    }

    /**
     * @api
     */
    public function report(Throwable $e): void
    {
        if (! $this->shouldSample || ! $this->enabled) {
            return;
        }

        try {
            $this->sensor->exception($e);
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
        if (! $this->shouldSample) {
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
        if (! $this->shouldSample) {
            return;
        }

        $this->sensor->queuedJob($event);
    }

    /**
     * @internal
     */
    public function notification(NotificationSending|NotificationSent $event): void
    {
        if (! $this->shouldSample) {
            return;
        }

        $this->sensor->notification($event);
    }

    /**
     * @internal
     */
    public function mail(MessageSending|MessageSent $event): void
    {
        if (! $this->shouldSample) {
            return;
        }

        $this->sensor->mail($event);
    }

    /**
     * @internal
     */
    public function cacheEvent(CacheEvent $event): void
    {
        if (! $this->shouldSample) {
            return;
        }

        $this->sensor->cacheEvent($event);
    }

    /**
     * @internal
     */
    public function stage(ExecutionStage $stage): void
    {
        if (! $this->shouldSample) {
            return;
        }

        $this->sensor->stage($stage);
    }

    /**
     * @internal
     */
    public function remember(Authenticatable $user): void
    {
        if (! $this->shouldSample) {
            return;
        }

        $this->state->user->remember($user);
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
    public function jobAttempt(JobAttempted $event): void
    {
        if (! $this->shouldSample) {
            return;
        }

        $this->sensor->jobAttempt($event);
    }

    /**
     * @internal
     */
    public function captureRequestPreview(Request $request): void
    {
        if (! $this->shouldSample) {
            return;
        }

        $this->state->executionPreview = Str::tinyText(
            $request->getMethod().' '.$request->getBaseUrl().$request->getPathInfo()
        );
    }

    /**
     * @internal
     */
    public function attachMiddlewareToRoute(Route $route): void
    {
        if (! $this->shouldSample) {
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
    public function configureForJobs(): void
    {
        $this->state->source = 'job';
    }

    /**
     * @internal
     */
    public function resetStateForNextJob(): void
    {
        $this->state->reset();
        memory_reset_peak_usage();
    }

    /**
     * @internal
     */
    public function prepareForJob(Job $job): void
    {
        $this->shouldSample = (bool) Compatibility::getHiddenContext('nightwatch_should_sample', true);

        if (! $this->shouldSample) {
            return;
        }

        $this->state->timestamp = $this->clock->microtime();
        $this->state->setId((string) Str::uuid());
        $this->state->executionPreview = Str::tinyText($job->resolveName());
    }

    /**
     * @internal
     */
    public function captureArtisan(Artisan $artisan): void
    {
        /** @var Core<CommandState> $this */
        $this->state->artisan = $artisan;
    }

    /**
     * @internal
     */
    public function prepareForCommand(string $name): void
    {
        /** @var Core<CommandState> $this */
        if (! $this->shouldSample) {
            return;
        }

        $this->state->name = $name;
        $this->state->executionPreview = Str::tinyText($name);
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
        $this->state->source = 'schedule';
    }

    /**
     * @internal
     */
    public function prepareForScheduledTask(): void
    {
        /*
         * Reset state for the current scheduled task execution.
         * Since `schedule:run` executes multiple tasks sequentially,
         * we need to clear previous task data to avoid metric pollution.
         */
        $this->state->reset();
        memory_reset_peak_usage();

        $trace = (string) Str::uuid();
        Compatibility::addHiddenContext('nightwatch_trace_id', $trace);
        $this->state->trace = $trace;
        $this->state->setId($trace);
        $this->state->timestamp = $this->clock->microtime();
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
    public function shouldCaptureLogs(): bool
    {
        return $this->shouldSample && $this->enabled;
    }
}
