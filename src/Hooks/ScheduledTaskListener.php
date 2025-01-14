<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\Contracts\LocalIngest;
use Laravel\Nightwatch\SensorManager;
use Laravel\Nightwatch\State\CommandState;
use Throwable;

/**
 * @internal
 */
final class ScheduledTaskListener
{
    public function __construct(
        private SensorManager $sensor,
        private CommandState $executionState,
        private LocalIngest $ingest,
    ) {
        //
    }

    public function __invoke(ScheduledTaskFinished|ScheduledTaskSkipped|ScheduledTaskFailed $event): void
    {
        try {
            if ($event instanceof ScheduledTaskFailed) {
                $this->sensor->exception($event->exception);
            }

            $this->sensor->scheduledTask($event);

            $this->ingest->write($this->executionState->records->flush());
        } catch (Throwable $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
