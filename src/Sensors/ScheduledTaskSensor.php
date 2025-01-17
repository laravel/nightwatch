<?php

namespace Laravel\Nightwatch\Sensors;

use Closure;
use DateTimeZone;
use Illuminate\Console\Application;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event as SchedulingEvent;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Records\ScheduledTask;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\Types\Str;
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
        $timezone = $event->task->timezone instanceof DateTimeZone ? $event->task->timezone->getName() : $event->task->timezone;

        if ($event instanceof ScheduledTaskSkipped) {
            $this->recordSkippedTask($event, $now, $name, $timezone);

            return;
        }

        $this->executionState->records->write(new ScheduledTask(
            timestamp: $this->executionState->timestamp,
            deploy: $this->executionState->deploy,
            server: $this->executionState->server,
            _group: hash('md5', "{$name},{$event->task->expression},{$timezone}"),
            trace_id: $this->executionState->trace,
            name: $name,
            cron: $event->task->expression,
            timezone: $timezone,
            without_overlapping: $event->task->withoutOverlapping,
            on_one_server: $event->task->onOneServer,
            run_in_background: $event->task->runInBackground,
            even_in_maintenance_mode: $event->task->evenInMaintenanceMode,
            status: match ($event::class) { // @phpstan-ignore-line match.unhandled
                ScheduledTaskFinished::class => 'processed',
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
        // @phpstan-ignore-next-line classConstant.nonObject
        return $callback::class;
    }

    /**
     * When a scheduled task is skipped, Laravel does not dispatch the `ScheduledTaskStarting` event.
     * Therefore, we need to manually generate a timestamp and trace ID for these tasks.
     */
    private function recordSkippedTask(ScheduledTaskSkipped $event, float $timestamp, string $name, string $timezone): void
    {
        $this->executionState->records->write(new ScheduledTask(
            timestamp: $timestamp,
            deploy: $this->executionState->deploy,
            server: $this->executionState->server,
            _group: hash('md5', "{$name},{$event->task->expression},{$timezone}"),
            trace_id: (string) Str::uuid(),
            name: $name,
            cron: $event->task->expression,
            timezone: $timezone,
            without_overlapping: $event->task->withoutOverlapping,
            on_one_server: $event->task->onOneServer,
            run_in_background: $event->task->runInBackground,
            even_in_maintenance_mode: $event->task->evenInMaintenanceMode,
            status: 'skipped',
            duration: 0,
            exceptions: 0,
            logs: 0,
            queries: 0,
            lazy_loads: 0,
            jobs_queued: 0,
            mail: 0,
            notifications: 0,
            outgoing_requests: 0,
            files_read: 0,
            files_written: 0,
            cache_events: 0,
            hydrated_models: 0,
            peak_memory_usage: 0,
        ));
    }
}
