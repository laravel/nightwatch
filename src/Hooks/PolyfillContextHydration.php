<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Queue\Events\JobProcessing;
use Laravel\Nightwatch\Compatibility;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Throwable;

/**
 * @internal
 */
final class PolyfillContextHydration
{
    /**
     * @param  Core<RequestState|CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(JobProcessing $event): void
    {
        try {
            $nightwatch = $event->job->payload()['nightwatch'] ?? [];

            Compatibility::$context = [
                'nightwatch_trace_id' => $nightwatch['nightwatch_trace_id'] ?? null,
                'nightwatch_should_sample' => $nightwatch['nightwatch_should_sample'] ?? null,
                'nightwatch_user_id' => $nightwatch['nightwatch_user_id'] ?? '',
            ];
        } catch (Throwable $e) {
            $this->nightwatch->report($e);

            Compatibility::$context = [];
        }
    }
}
