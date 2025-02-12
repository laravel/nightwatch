<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Queue\Events\JobPopping;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\CommandState;
use Throwable;

use function memory_reset_peak_usage;

/**
 * @internal
 */
final class JobPoppingListener
{
    /**
     * @param  Core<CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(JobPopping $event): void
    {
        try {
            $this->nightwatch->state->reset();
            memory_reset_peak_usage();
        } catch (Throwable $e) {
            $this->nightwatch->report($e);
        }
    }
}
