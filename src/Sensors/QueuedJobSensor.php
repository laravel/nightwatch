<?php

namespace Laravel\Nightwatch\Sensors;

use Illuminate\Events\CallQueuedListener;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\JobQueueing;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Compatibility;
use Laravel\Nightwatch\Concerns\NormalizesQueue;
use Laravel\Nightwatch\Records\QueuedJob;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Laravel\Nightwatch\Types\Str;
use ReflectionClass;

use function hash;
use function is_object;
use function is_string;
use function method_exists;
use function property_exists;
use function round;

/**
 * @internal
 */
final class QueuedJobSensor
{
    use NormalizesQueue;

    private ?float $startTime = null;

    /**
     * @param  array<string, array{ queue?: string, driver?: string, prefix?: string, suffix?: string }>  $connectionConfig
     */
    public function __construct(
        private RequestState|CommandState $executionState,
        private Clock $clock,
        private array $connectionConfig,
    ) {
        //
    }

    /**
     * @return ?array{0: QueuedJob, 1: callable(): array<mixed>}
     */
    public function __invoke(JobQueueing|JobQueued $event): ?array
    {
        $now = $this->clock->microtime();

        if ($event instanceof JobQueueing) {
            $this->startTime = $now;

            return null;
        }

        $name = match (true) {
            is_string($event->job) => $event->job,
            method_exists($event->job, 'displayName') => $event->job->displayName(),
            default => $event->job::class,
        };

        return [
            $record = new QueuedJob(
                jobId: $event->payload()['nightwatch']['job_id'] ?? $event->payload()['uuid'],
                name: $name,
                connection: $event->connectionName,
                queue: $this->normalizeQueue($event->connectionName, $this->resolveQueue($event)),
                duration: Compatibility::$queuedJobDurationCapturable ? (int) round(($now - $this->startTime) * 1_000_000) : 0,
            ),
            function () use ($now, $record) {
                $this->executionState->jobsQueued++;

                return [
                    'v' => 1,
                    't' => 'queued-job',
                    'timestamp' => $now,
                    'deploy' => $this->executionState->deploy,
                    'server' => $this->executionState->server,
                    '_group' => hash('xxh128', $record->name),
                    'trace_id' => $this->executionState->trace,
                    'execution_source' => $this->executionState->source,
                    'execution_id' => $this->executionState->id(),
                    'execution_preview' => $this->executionState->executionPreview(),
                    'execution_stage' => $this->executionState->stage,
                    'user' => $this->executionState->user->id(),
                    'job_id' => $record->jobId,
                    'name' => Str::text($record->name),
                    'connection' => Str::tinyText($record->connection),
                    'queue' => Str::tinyText($record->queue),
                    'duration' => $record->duration,
                ];
            },
        ];
    }

    private function resolveQueue(JobQueued $event): string
    {
        if (! Compatibility::$queueNameCapturable) {
            return '';
        }

        /**
         * This property has not always had the correct type. It was missing,
         * added, removed, and re-added through time. We will force the type
         * here so we know what we are dealing with across all versions.
         *
         * @see https://github.com/laravel/framework/pull/55058
         *
         * @var string|null $queue
         */
        $queue = $event->queue;

        if ($queue !== null) {
            return $queue;
        }

        if (is_object($event->job)) {
            if (property_exists($event->job, 'queue') && $event->job->queue !== null) {
                return $event->job->queue;
            }

            if ($event->job instanceof CallQueuedListener) {
                $queue = $this->resolveQueuedListenerQueue($event->job);
            }
        }

        return $queue ?? $this->connectionConfig[$event->connectionName]['queue'] ?? '';
    }

    private function resolveQueuedListenerQueue(CallQueuedListener $listener): ?string
    {
        $reflectionJob = (new ReflectionClass($listener->class))->newInstanceWithoutConstructor();

        if (method_exists($reflectionJob, 'viaQueue')) {
            return $reflectionJob->viaQueue($listener->data[0] ?? null);
        }

        return $reflectionJob->queue ?? null;
    }
}
