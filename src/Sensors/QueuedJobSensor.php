<?php

namespace Laravel\Nightwatch\Sensors;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Queue\Events\JobQueued;
use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Records\ExecutionState;
use Laravel\Nightwatch\Records\QueuedJob;
use Laravel\Nightwatch\UserProvider;
use ReflectionClass;

use function array_key_exists;
use function hash;
use function is_object;
use function is_string;
use function method_exists;
use function preg_quote;
use function preg_replace;
use function property_exists;

/**
 * @internal
 */
final class QueuedJobSensor
{
    /**
     * @var array<string, string>
     */
    private array $defaultQueues = [];

    /**
     * @var array<string, string>
     */
    private array $normalizedQueues = [];

    public function __construct(
        private RecordsBuffer $recordsBuffer,
        private ExecutionState $executionState,
        private UserProvider $user,
        private Clock $clock,
        private Config $config,
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

        $this->executionState->jobs_queued++;

        $this->recordsBuffer->writeQueuedJob(new QueuedJob(
            timestamp: (int) $nowMicrotime,
            deploy: $this->executionState->deploy,
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
            queue: $this->normalizeSqsQueue($event->connectionName, $this->resolveQueue($event)),
        ));
    }

    private function resolveQueue(JobQueued $event): string
    {
        /** @var string|null */
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

        return $queue ?? $this->defaultQueue($event->connectionName);
    }

    private function normalizeSqsQueue(string $connection, string $queue): string
    {
        $key = "{$connection}:{$queue}";

        if (array_key_exists($key, $this->normalizedQueues)) {
            return $this->normalizedQueues[$key];
        }

        $config = $this->config->get("queue.connections.{$connection}") ?? [];

        if (($config['driver'] ?? '') !== 'sqs') {
            return $this->normalizedQueues[$key] = $queue;
        }

        if ($config['prefix'] ?? false) {
            $prefix = preg_quote($config['prefix'], '#');

            $queue = preg_replace("#^{$prefix}/#", '', $queue) ?? $queue;
        }

        if ($config['suffix'] ?? false) {
            $suffix = preg_quote($config['suffix'], '#');

            $queue = preg_replace("#{$suffix}$#", '', $queue) ?? $queue;
        }

        return $this->normalizedQueues[$key] = $queue;
    }

    private function resolveQueuedListenerQueue(CallQueuedListener $listener): ?string
    {
        $reflectionJob = (new ReflectionClass($listener->class))->newInstanceWithoutConstructor(); // @phpstan-ignore argument.type

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
