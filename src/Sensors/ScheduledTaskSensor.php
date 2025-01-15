<?php

namespace Laravel\Nightwatch\Sensors;

use Closure;
use Illuminate\Console\Application;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event as SchedulingEvent;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Records\ScheduledTask;
use Laravel\Nightwatch\State\CommandState;
use ReflectionClass;
use ReflectionFunction;

use function base_path;
use function hash;
use function in_array;
use function is_array;
use function is_string;
use function preg_replace;
use function round;
use function sprintf;
use function str_replace;

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
        $name = $this->normalizeTaskName($event->task);

        $this->executionState->records->write(new ScheduledTask(
            timestamp: $this->executionState->timestamp,
            deploy: $this->executionState->deploy,
            server: $this->executionState->server,
            _group: hash('md5', "{$name},{$event->task->expression},{$event->task->timezone}"),
            trace_id: $this->executionState->trace,
            name: $name,
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

    private function normalizeTaskName(SchedulingEvent $event): string
    {
        $name = $event->command ?? '';

        $name = str_replace([
            Application::phpBinary(),
            Application::artisanBinary(),
        ], [
            'php',
            preg_replace("#['\"]#", '', Application::artisanBinary()),
        ], $name);

        if ($event instanceof CallbackEvent) {
            $name = $event->getSummaryForDisplay();

            if (in_array($name, ['Closure', 'Callback'], true)) {
                $name = $this->getClosureLocation($event);
            }
        }

        return $name;
    }

    /**
     * Get the file and line number for the event closure.
     */
    private function getClosureLocation(CallbackEvent $event): string
    {
        $callback = (new ReflectionClass($event))->getProperty('callback')->getValue($event);

        if ($callback instanceof Closure) {
            $function = new ReflectionFunction($callback);

            return sprintf(
                'Closure at: %s:%s',
                // TODO: Replace with `$this->core->app->basePath()`.
                str_replace(base_path().DIRECTORY_SEPARATOR, '', $function->getFileName() ?: ''),
                $function->getStartLine()
            );
        }

        if (is_string($callback)) {
            return $callback;
        }

        if (is_array($callback)) {
            return is_string($callback[0]) ? $callback[0] : $callback[0]::class;
        }

        // Invokable class
        return $callback::class;
    }
}
