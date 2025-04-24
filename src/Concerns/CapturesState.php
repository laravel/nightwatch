<?php

namespace Laravel\Nightwatch\Concerns;

use Illuminate\Cache\Events\CacheEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\JobQueueing;
use Illuminate\Routing\Route;
use Laravel\Nightwatch\Compatibility;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Hooks\GlobalMiddleware;
use Laravel\Nightwatch\Hooks\RouteMiddleware;
use Laravel\Nightwatch\Types\Str;
use Monolog\LogRecord;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use WeakMap;

use function array_shift;
use function array_unshift;
use function debug_backtrace;
use function random_int;

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
     */
    public function configureRequestSampling(): void
    {
        $this->shouldSample = (random_int(0, PHP_INT_MAX) / PHP_INT_MAX) <= $this->sampling['requests'];

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
    public function shouldCaptureLogs(): bool
    {
        return $this->shouldSample && $this->enabled;
    }
}
