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
use Laravel\Nightwatch\Concerns\RecordsContext;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\Support\Uuid;
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
    use RecordsContext;

    public function __construct(
        private CommandState $commandState,
        private Uuid $uuid,
        private Clock $clock,
    ) {
        //
    }

    /**
     * @return ?array<mixed>
     */
    public function __invoke(ScheduledTaskFinished|ScheduledTaskSkipped|ScheduledTaskFailed $event): ?array
    {
        $now = $this->clock->microtime();
        $name = $this->normalizeTaskName($event->task);
        $timezone = $event->task->timezone instanceof DateTimeZone ? $event->task->timezone->getName() : $event->task->timezone;

        if ($event instanceof ScheduledTaskSkipped) {
            return $this->recordSkippedTask($event, $now, $name, $timezone);
        }

        return [
            'v' => 1,
            't' => 'scheduled-task',
            'timestamp' => $this->commandState->timestamp,
            'deploy' => $this->commandState->deploy,
            'server' => $this->commandState->server,
            '_group' => hash('xxh128', "{$name},{$event->task->expression},{$timezone}"),
            'trace_id' => $this->commandState->trace,
            // --- //
            'name' => $name,
            'cron' => $event->task->expression,
            'timezone' => $timezone,
            'without_overlapping' => $event->task->withoutOverlapping,
            'on_one_server' => $event->task->onOneServer,
            'run_in_background' => $event->task->runInBackground,
            'even_in_maintenance_mode' => $event->task->evenInMaintenanceMode,
            'status' => match ($event::class) { // @phpstan-ignore-line match.unhandled
                ScheduledTaskFinished::class => 'processed',
                ScheduledTaskFailed::class => 'failed',
            },
            'duration' => (int) round(($now - $this->commandState->timestamp) * 1_000_000),
            // --- //
            'exceptions' => $this->commandState->exceptions,
            'logs' => $this->commandState->logs,
            'queries' => $this->commandState->queries,
            'lazy_loads' => $this->commandState->lazyLoads,
            'jobs_queued' => $this->commandState->jobsQueued,
            'mail' => $this->commandState->mail,
            'notifications' => $this->commandState->notifications,
            'outgoing_requests' => $this->commandState->outgoingRequests,
            'files_read' => $this->commandState->filesRead,
            'files_written' => $this->commandState->filesWritten,
            'cache_events' => $this->commandState->cacheEvents,
            'hydrated_models' => $this->commandState->hydratedModels,
            'peak_memory_usage' => $this->commandState->peakMemory(),
            'exception_preview' => Str::tinyText($this->commandState->exceptionPreview),
            'context' => $this->serializedContext(),
        ];
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
     *
     * @return array<mixed>
     */
    private function recordSkippedTask(ScheduledTaskSkipped $event, float $timestamp, string $name, string $timezone): array
    {
        return [
            'v' => 1,
            't' => 'scheduled-task',
            'timestamp' => $timestamp,
            'deploy' => $this->commandState->deploy,
            'server' => $this->commandState->server,
            '_group' => hash('xxh128', "{$name},{$event->task->expression},{$timezone}"),
            'trace_id' => $this->uuid->make(),
            // --- //
            'name' => $name,
            'cron' => $event->task->expression,
            'timezone' => $timezone,
            'without_overlapping' => $event->task->withoutOverlapping,
            'on_one_server' => $event->task->onOneServer,
            'run_in_background' => $event->task->runInBackground,
            'even_in_maintenance_mode' => $event->task->evenInMaintenanceMode,
            'status' => 'skipped',
            'duration' => 0,
            // --- //
            'exceptions' => 0,
            'logs' => 0,
            'queries' => 0,
            'lazy_loads' => 0,
            'jobs_queued' => 0,
            'mail' => 0,
            'notifications' => 0,
            'outgoing_requests' => 0,
            'files_read' => 0,
            'files_written' => 0,
            'cache_events' => 0,
            'hydrated_models' => 0,
            'peak_memory_usage' => 0,
            'exception_preview' => '',
            'context' => '',
        ];
    }
}
