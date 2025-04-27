<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Queue\Events\JobAttempted;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\CommandState;
use Throwable;

use function dump;

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
    ) {
        //
    }

    public function __invoke(JobAttempted $event): void
    {
        try {
            $this->nightwatch->jobAttempt($event);
            dump('jobAttempt');
        } catch (Throwable $e) {
            $this->nightwatch->report($e);
        }

        dump('ingest');

        $this->nightwatch->ingest();
    }
}
