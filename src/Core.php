<?php

namespace Laravel\Nightwatch;

use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Nightwatch\Contracts\Ingest;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Hooks\GuzzleMiddleware;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Laravel\Nightwatch\Support\Uuid;
use Throwable;
use WeakMap;

/**
 * @template TState of RequestState|CommandState
 */
final class Core
{
    use Concerns\CapturesState,
        Concerns\RedactsRecords,
        Concerns\RejectsRecords;

    /**
     * @internal
     *
     * @var null|(callable(Authenticatable): array{id: mixed, name?: mixed, username?: mixed})
     */
    public $userDetailsResolver = null;

    /**
     * @param  TState  $executionState
     * @param  array{
     *     enabled: bool,
     *     sampling: array{
     *         requests: float,
     *         commands: float,
     *         exceptions: float,
     *     },
     *     filtering: array{
     *         ignore_cache_events: bool,
     *         ignore_mail: bool,
     *         ignore_notifications: bool,
     *         ignore_outgoing_requests: bool,
     *         ignore_queries: bool,
     *     },
     * }  $config
     */
    public function __construct(
        public Ingest $ingest,
        public SensorManager $sensor,
        public RequestState|CommandState $executionState,
        public Clock $clock,
        public Uuid $uuid,
        public array $config,
    ) {
        $this->routesWithMiddlewareRegistered = new WeakMap;
    }

    /**
     * @api
     */
    public function user(callable $callback): void
    {
        $this->userDetailsResolver = $callback;
    }

    /**
     * @api
     */
    public function guzzleMiddleware(): callable
    {
        return new GuzzleMiddleware($this);
    }

    /**
     * @internal
     *
     * @return $this
     */
    public function digest(): self
    {
        if ($this->waitingForJob) {
            return $this;
        }

        try {
            $this->ingest->digest();
        } catch (Throwable $e) {
            Nightwatch::unrecoverableExceptionOccurred($e);
        }

        return $this;
    }

    /**
     * @internal
     */
    public function enabled(): bool
    {
        return $this->config['enabled'];
    }
}
