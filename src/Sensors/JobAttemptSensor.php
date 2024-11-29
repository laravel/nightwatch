<?php

namespace Laravel\Nightwatch\Sensors;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Concerns\NormalizesQueue;
use Laravel\Nightwatch\Records\ExecutionState;
use Laravel\Nightwatch\Records\JobAttempt;
use Laravel\Nightwatch\UserProvider;
use phpDocumentor\Reflection\Types\This;
use RuntimeException;

/**
 * @internal
 */
final class JobAttemptSensor
{
    use NormalizesQueue;

    private ?float $startTime = null;

    /**
     * @param  array<string, array{ queue?: string, driver?: string, prefix?: string, suffix?: string }>  $connectionConfig
     */
    public function __construct(
        private ExecutionState $executionState,
        private UserProvider $user,
        private Clock $clock,
        private array $connectionConfig,
    ) {
        //
    }

    public function __invoke(JobProcessing|JobProcessed|JobReleasedAfterException|JobFailed $event): void
    {
        if ($event->connectionName === 'sync') {
            return;
        }

        $now = $this->clock->microtime();

        if ($event::class === JobProcessing::class) {
            $this->startTime = $now;

            return;
        }

        if ($this->startTime === null) {
            throw new RuntimeException('No start time found for ['.$event::class.'].');
        }

        $this->executionState->records->write(new JobAttempt(
            timestamp: $this->startTime,
            deploy: $this->executionState->deploy,
            server: $this->executionState->server,
            _group: hash('md5', $event->job->resolveName()),
            trace_id: $this->executionState->trace,
            user: $this->user->id(),
            job_id: $event->job->getJobId(), // TODO: Seems like both the id and the uuid are the job identifier
            attempt_id: $event->job->uuid(), // TODO: Is there any identifier for the attempt?
            attempt: $event->job->attempts(),
            name: $event->job->resolveName(),
            connection: $event->job->getConnectionName(),
            queue: $this->normalizeQueue($event->job->getConnectionName(), $event->job->getQueue()),
            status: match ($event::class) {
                JobProcessed::class => 'processed',
                JobReleasedAfterException::class => 'released',
                JobFailed::class => 'failed',
            },
            duration: (int) round(($now - $this->startTime) * 1_000_000),
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

        $this->startTime = null;
    }
}
