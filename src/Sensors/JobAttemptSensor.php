<?php

namespace Laravel\Nightwatch\Sensors;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Records\ExecutionState;
use Laravel\Nightwatch\Records\JobAttempt;
use Laravel\Nightwatch\UserProvider;

/**
 * @internal
 */
final class JobAttemptSensor
{
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

    public function __invoke(JobProcessed|JobReleasedAfterException|JobFailed $event): void
    {
        if ($event->connectionName === 'sync') {
            return;
        }

        $this->executionState->records->write(new JobAttempt(
            timestamp: '', // TODO
            deploy: $this->executionState->deploy,
            server: $this->executionState->server,
            _group: hash('md5', ''), // TODO
            trace_id: $this->executionState->trace,
            user: $this->user->id(),
            job_id: $event->job->getJobId(), // TODO: Which is the job id and which is the attempt id?
            attempt_id: $event->job->uuid(), // TODO
            attempt: $event->job->attempts(),
            name: $event->job->resolveName(),
            connection: $event->job->getConnectionName(),
            queue: $event->job->getQueue(),
            status: match ($event::class) {
                JobProcessed::class => 'processed',
                JobReleasedAfterException::class => 'released',
                JobFailed::class => 'failed',
            },
            duration: 0, // TODO: Calculate duration
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
}
