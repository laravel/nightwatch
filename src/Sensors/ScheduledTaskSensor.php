<?php

namespace Laravel\Nightwatch\Sensors;

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Records\ScheduledTask;
use Laravel\Nightwatch\State\CommandState;

use function hash;
use function round;

/**
 * @internal
 */
final class ScheduledTaskSensor
{
    public function __construct(
        private CommandState $executionState,
        private Clock $clock,
    ) {
        //
    }

    public function __invoke(ScheduledTaskFinished|ScheduledTaskSkipped|ScheduledTaskFailed $event): void
    {
        $now = $this->clock->microtime();

        $this->executionState->records->write(new ScheduledTask(
            timestamp: $this->executionState->timestamp,
            deploy: $this->executionState->deploy,
            server: $this->executionState->server,
            _group: hash('md5', "{$event->task->command},{$event->task->expression},{$event->task->timezone}"),
            trace_id: $this->executionState->trace,
            name: $event->task->command, // TODO: Can be `command`, `description`, or `callback` depending on the event
            cron: $event->task->expression,
            timezone: $event->task->timezone,
            without_overlapping: $event->task->withoutOverlapping,
            on_one_server: $event->task->onOneServer,
            run_in_background: $event->task->runInBackground,
            even_in_maintenance_mode: $event->task->evenInMaintenanceMode,
            status: match ($event::class) {
                ScheduledTaskFinished::class => 'processed',
                ScheduledTaskSkipped::class => 'skipped',
                ScheduledTaskFailed::class => 'failed',
            },
            duration: (int) round(($now - $this->executionState->timestamp) * 1_000_000),
            exceptions: $this->executionState->exceptions,
            logs: $this->executionState->logs,
            queries: $this->executionState->queries,
            lazy_loads: $this->executionState->lazyLoads,
            jobs_queued: $this->executionState->jobsQueued,
            mail: $this->executionState->mail,
            notifications: $this->executionState->notifications,
            outgoing_requests: $this->executionState->outgoingRequests,
            files_read: $this->executionState->filesRead,
            files_written: $this->executionState->filesWritten,
            cache_events: $this->executionState->cacheEvents,
            hydrated_models: $this->executionState->hydratedModels,
            peak_memory_usage: $this->executionState->peakMemory(),
        ));
    }
}
