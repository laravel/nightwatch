<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\Contracts\LocalIngest;
use Laravel\Nightwatch\SensorManager;
use Laravel\Nightwatch\State\CommandState;
use Throwable;

/**
 * @internal
 */
final class JobAttemptedListener
{
    public function __construct(
        private SensorManager $sensor,
        private CommandState $executionState,
        private LocalIngest $ingest,
    ) {
        //
    }

    public function __invoke(JobAttempted $event): void
    {
        try {
            $this->sensor->jobAttempt($event);

            $this->ingest->write($this->executionState->records->flush());
        } catch (Throwable $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
