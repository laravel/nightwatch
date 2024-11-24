<?php

namespace Laravel\Nightwatch\Sensors;

use Illuminate\Events\CallQueuedListener;
use Illuminate\Queue\Events\JobQueued;
use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Records\ExecutionState;
use Laravel\Nightwatch\Records\QueuedJob;
use Laravel\Nightwatch\UserProvider;
use ReflectionClass;

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
     * TODO memory leak?
     *
     * @var array<string, array<string, string>>
     */
    private array $normalizedQueues = [];

    /**
     * @param  array<string, array{ queue?: string, driver?: string, prefix?: string, suffix?: string }>  $connectionConfig
     */
    public function __construct(
        private RecordsBuffer $recordsBuffer,
        private ExecutionState $executionState,
        private UserProvider $user,
        private Clock $clock,
        private array $connectionConfig,
    ) {
        //
    }

    public function __invoke(JobQueued $event): void
    {
        $nowMicrotime = $this->clock->microtime();

        $this->executionState->jobs_queued++;

        $this->recordsBuffer->write(new QueuedJob(
            timestamp: $nowMicrotime,
            deploy: $this->executionState->deploy,
            server: $this->executionState->server,
            group: hash('sha256', ''), // TODO
            trace_id: $this->executionState->trace,
            execution_context: $this->executionState->context,
            execution_id: $this->executionState->id,
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

    private function normalizeSqsQueue(string $connection, string $queue): string
    {
        $key = "{$connection}:{$queue}";

        if (isset($this->normalizedQueues[$connection][$queue])) {
            return $this->normalizedQueues[$connection][$queue];
        }

        $config = $this->connectionConfig[$connection] ?? [];

        if (($config['driver'] ?? '') !== 'sqs') {
            return $this->normalizedQueues[$connection][$key] = $queue;
        }

        if ($config['prefix'] ?? false) {
            $prefix = preg_quote($config['prefix'], '#');

            $queue = preg_replace("#^{$prefix}/#", '', $queue) ?? $queue;
        }

        if ($config['suffix'] ?? false) {
            $suffix = preg_quote($config['suffix'], '#');

            $queue = preg_replace("#{$suffix}$#", '', $queue) ?? $queue;
        }

        return $this->normalizedQueues[$connection][$key] = $queue;
    }

    private function resolveQueuedListenerQueue(CallQueuedListener $listener): ?string
    {
        /**
         * We can remove this once we have better types in the framework.
         *
         * @see https://github.com/laravel/framework/pull/53657
         *
         * @phpstan-ignore-next-line argument.type
         */
        $reflectionJob = (new ReflectionClass($listener->class))->newInstanceWithoutConstructor();

        if (method_exists($reflectionJob, 'viaQueue')) {
            return $reflectionJob->viaQueue($listener->data[0] ?? null);
        }

        return $reflectionJob->queue ?? null;
    }
}
