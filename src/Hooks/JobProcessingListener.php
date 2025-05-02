<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Queue\Events\JobProcessing;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\CommandState;
use Throwable;

/**
 * @internal
 */
final class JobProcessingListener
{
    /**
     * @param  Core<CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(JobProcessing $event): void
    {
        try {
            $this->nightwatch->prepareForJob($event->job);
        } catch (Throwable $e) {
            $this->nightwatch->report($e);
        }
    }
}
