<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Laravel\Nightwatch\Compatibility;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\CommandState;
use Throwable;

/**
 * @internal
 */
final class ScheduledTaskListener
{
    /**
     * @param  Core<CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(ScheduledTaskFinished|ScheduledTaskSkipped|ScheduledTaskFailed $event): void
    {
        // We report the exception here because the scheduler handles it after the task has finished and the data is ingested.
        // This ensures that the exception is captured in the scheduled task record.
        if ($event instanceof ScheduledTaskFailed) {
            $this->nightwatch->report($event->exception);
        }

        if ($this->isFinishedEventForFailedTask($event)) {
            return;
        }

        if ($event instanceof ScheduledTaskSkipped) {
            $this->nightwatch->prepareForNextScheduledTask($event->task);
        }

        try {
            $this->nightwatch->scheduledTask($event);
        } catch (Throwable $e) {
            $this->nightwatch->report($e, handled: true);
        }

        $this->nightwatch->finishExecution()->waitForExecution();
    }

    private function isFinishedEventForFailedTask(ScheduledTaskFinished|ScheduledTaskSkipped|ScheduledTaskFailed $event): bool
    {
        return Compatibility::$firesFinishedAndFailedEventsForScheduledConsoleCommands &&
            $event instanceof ScheduledTaskFinished &&
            $event->task->command !== null &&
            $event->task->exitCode !== 0 &&
            ! $event->task->runInBackground;
    }
}
