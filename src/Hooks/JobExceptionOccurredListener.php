<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Queue\Events\JobExceptionOccurred;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\CommandState;

/**
 * @internal
 */
final class JobExceptionOccurredListener
{
    /**
     * @param  Core<CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(JobExceptionOccurred $event): void
    {
        $this->nightwatch->report($event->exception);
    }
}
