<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Queue\Events\JobAttempted;
use Laravel\Nightwatch\Contracts\LocalIngest;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\CommandState;
use Throwable;

/**
 * @internal
 */
final class JobAttemptedListener
{
    /**
     * @param  Core<CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
        private LocalIngest $ingest,
    ) {
        //
    }

    public function __invoke(JobAttempted $event): void
    {
        try {
            $this->nightwatch->sensor->jobAttempt($event);
        } catch (Throwable $e) {
            $this->nightwatch->report($e);
        }

        try {
            $this->ingest->write($this->nightwatch->state->records->flush());
        } catch (Throwable $e) {
            $this->nightwatch->handleUnrecoverableException($e);
        }
    }
}
