<?php

namespace Laravel\Nightwatch\Sensors;

use Illuminate\Events\CallQueuedListener;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\JobQueueing;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Concerns\NormalizesQueue;
use Laravel\Nightwatch\Records\QueuedJob;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use ReflectionClass;
use RuntimeException;

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

    private ?int $duration = null;

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

    public function __invoke(JobQueueing|JobQueued $event): void
    {
        $now = $this->clock->microtime();

        if ($event instanceof JobQueueing) {
            $this->startTime = $now;

            return;
        }

        $name = match (true) {
            is_string($event->job) => $event->job,
            method_exists($event->job, 'displayName') => $event->job->displayName(),
            default => $event->job::class,
        };

        if ($this->startTime === null) {
            throw new RuntimeException("No start time found for [{$name}].");
        }

        $this->duration = (int) round(($now - $this->startTime) * 1_000_000);
        $this->executionState->jobsQueued++;

        $this->executionState->records->write(new QueuedJob(
            timestamp: $now,
            deploy: $this->executionState->deploy,
            server: $this->executionState->server,
            _group: hash('md5', $name),
            trace_id: $this->executionState->trace,
            execution_source: $this->executionState->source,
            execution_id: $this->executionState->id(),
            execution_stage: $this->executionState->stage,
            user: $this->executionState->user->id(),
            job_id: $event->payload()['uuid'],
            name: $name,
            connection: $event->connectionName,
            queue: $this->normalizeQueue($event->connectionName, $this->resolveQueue($event)),
            duration: $this->duration,
        ));
    }

    private function resolveQueue(JobQueued $event): string
    {
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
