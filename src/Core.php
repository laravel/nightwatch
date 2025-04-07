<?php

namespace Laravel\Nightwatch;

use Laravel\Nightwatch\Contracts\LocalIngest;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Throwable;

/**
 * @template TState of RequestState|CommandState
 */
final class Core
{
    /**
     * @param  TState  $state
     */
    public function __construct(
        public LocalIngest $ingest,
        public SensorManager $sensor,
        public RequestState|CommandState $state,
        public Clock $clock,
        public bool $enabled,
    ) {
        //
    }

    public function report(Throwable $e): void
    {
        if (! $this->enabled) {
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
    public function ingest(): void
    {
        try {
            $this->ingest->write($this->state->records->flush());
        } catch (Throwable $e) {
            Nightwatch::unrecoverableExceptionOccurred($e);
        }
    }
}
