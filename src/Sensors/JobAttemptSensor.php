<?php

namespace Laravel\Nightwatch\Sensors;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Illuminate\Support\Facades\Context;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Concerns\NormalizesQueue;
use Laravel\Nightwatch\Records\JobAttempt;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\Types\Str;
use Laravel\Nightwatch\UserProvider;

/**
 * @internal
 */
final class JobAttemptSensor
{
    use NormalizesQueue;

    /**
     * @param  array<string, array{ queue?: string, driver?: string, prefix?: string, suffix?: string }>  $connectionConfig
     */
    public function __construct(
        private CommandState $executionState,
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

        $now = $this->clock->microtime();

        $this->executionState->records->write(new JobAttempt(
            timestamp: $this->executionState->timestamp,
            deploy: $this->executionState->deploy,
            server: $this->executionState->server,
            _group: hash('md5', $event->job->resolveName()),
            trace_id: Context::getHidden('nightwatch_trace_id'),
            user: $this->user->id(),
            job_id: $event->job->uuid(),
            attempt_id: (string) Str::uuid(),
            attempt: $event->job->attempts(),
            name: $event->job->resolveName(),
            connection: $event->job->getConnectionName(),
            queue: $this->normalizeQueue($event->job->getConnectionName(), $event->job->getQueue()),
            status: match ($event::class) {
                JobProcessed::class => 'processed',
                JobReleasedAfterException::class => 'released',
                JobFailed::class => 'failed',
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
}
