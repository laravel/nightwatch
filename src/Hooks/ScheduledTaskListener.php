<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\Core;
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
        try {
            if ($event instanceof ScheduledTaskFailed) {
                $this->nightwatch->sensor->exception($event->exception);
            }

            $this->nightwatch->sensor->scheduledTask($event);
            $this->nightwatch->ingest();
        } catch (Throwable $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
