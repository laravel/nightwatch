<?php

namespace Laravel\Nightwatch\Sensors;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Queue\Events\JobQueued;
use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\Contracts\Clock;
use Laravel\Nightwatch\Records\ExecutionParent;
use Laravel\Nightwatch\Records\QueuedJob;
use Laravel\Nightwatch\UserProvider;
use ReflectionClass;

final class QueuedJobSensor
{
    /**
     * @var array<string, string>
     */
    private array $defaultQueues = [];

    public function __construct(
        private RecordsBuffer $recordsBuffer,
        private ExecutionParent $executionParent,
        private UserProvider $user,
        private Clock $clock,
        private Config $config,
        private string $deployId,
        private string $server,
        private string $traceId,
    ) {
        //
    }

    /**
     * TODO group, execution_context, execution_id
     */
    public function __invoke(JobQueued $event): void
    {
        $nowMicrotime = $this->clock->microtime();

        $this->executionParent->jobs_queued++;

        $this->recordsBuffer->writeQueuedJob(new QueuedJob(
            timestamp: (int) $nowMicrotime,
            deploy_id: $this->deployId,
            server: $this->server,
            group: hash('sha256', ''),
            trace_id: $this->traceId,
            execution_context: 'request',
            execution_id: '00000000-0000-0000-0000-000000000000',
            execution_offset: $this->clock->executionOffset($nowMicrotime),
            user: $this->user->id(),
            job_id: $event->payload()['uuid'],
            name: match (true) {
                is_string($event->job) => $event->job,
                method_exists($event->job, 'displayName') => $event->job->displayName(),
                default => $event->job::class,
            },
            connection: $event->connectionName,
            queue: $this->resolveQueue($event) ?? $this->defaultQueue($event->connectionName),
        ));
    }

    private function resolveQueue(JobQueued $event): ?string
    {
        $isObject = is_object($event->job);

        if ($isObject && $event->job instanceof CallQueuedListener) {
            return $this->resolveQueuedListenerQueue($event->job);
        }

        if ($isObject && property_exists($event->job, 'queue')) {
            return $event->job->queue ?? null;
        }

        return null;
    }

    private function resolveQueuedListenerQueue(CallQueuedListener $listener): ?string
    {
        $reflectionJob = (new ReflectionClass($listener->class))->newInstanceWithoutConstructor();

        if (method_exists($reflectionJob, 'viaQueue')) {
            return $reflectionJob->viaQueue($listener->data[0] ?? null);
        }

        return $reflectionJob->queue ?? null;
    }

    private function defaultQueue(string $connection): string
    {
        return $this->defaultQueues[$connection] ??= $this->config->get('queue.connections.'.$connection.'.queue');
    }
}
