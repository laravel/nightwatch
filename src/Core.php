<?php

namespace Laravel\Nightwatch;

use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Nightwatch\Contracts\LocalIngest;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Hooks\GuzzleMiddleware;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Throwable;
use WeakMap;

/**
 * @template TState of RequestState|CommandState
 */
final class Core
{
    use Concerns\CapturesState;

    /**
     * @internal
     *
     * @var null|(callable(Authenticatable): array{id: mixed, name?: mixed, username?: mixed})
     */
    public $userDetailsResolver = null;

    /**
     * @param  TState  $state
     * @param  array{ requests: float, commands: float }  $sampling
     */
    public function __construct(
        public LocalIngest $ingest,
        public SensorManager $sensor,
        public RequestState|CommandState $state,
        public Clock $clock,
        public bool $enabled,
        public array $sampling,
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
     */
    public function ingest(): void
    {
        if (! $this->shouldSample) {
            return;
        }

        try {
            $this->ingest->write($this->state->records->pull());
        } catch (Throwable $e) {
            Nightwatch::unrecoverableExceptionOccurred($e);
        }
    }
}
