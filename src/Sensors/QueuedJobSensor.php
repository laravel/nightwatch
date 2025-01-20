<?php

namespace Laravel\Nightwatch\Sensors;

use Illuminate\Events\CallQueuedListener;
use Illuminate\Queue\Events\JobQueued;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Concerns\NormalizesQueue;
use Laravel\Nightwatch\Records\QueuedJob;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use ReflectionClass;

use function hash;
use function is_object;
use function is_string;
use function method_exists;
use function property_exists;

/**
 * @internal
 */
final class QueuedJobSensor
{
    use NormalizesQueue;

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

    public function __invoke(JobQueued $event): void
    {
        $nowMicrotime = $this->clock->microtime();
        $name = match (true) {
            is_string($event->job) => $event->job,
            method_exists($event->job, 'displayName') => $event->job->displayName(),
            default => $event->job::class,
        };

        $this->executionState->jobsQueued++;

        $this->executionState->records->write(new QueuedJob(
            timestamp: $nowMicrotime,
            deploy: $this->executionState->deploy,
            server: $this->executionState->server,
            _group: hash('md5', $name),
            trace_source: $this->executionState->traceSource,
            trace_id: $this->executionState->traceId,
            execution_source: $this->executionState->executionSource,
            execution_id: $this->executionState->executionId,
            execution_stage: $this->executionState->stage,
            user: $this->executionState->user->id(),
            job_id: $event->payload()['uuid'],
            name: $name,
            connection: $event->connectionName,
            queue: $this->normalizeQueue($event->connectionName, $this->resolveQueue($event)),
            duration: 0, // TODO
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
