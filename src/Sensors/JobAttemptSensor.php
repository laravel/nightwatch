<?php

namespace Laravel\Nightwatch\Sensors;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Concerns\NormalizesQueue;
use Laravel\Nightwatch\Contracts\LocalIngest;
use Laravel\Nightwatch\LazyValue;
use Laravel\Nightwatch\Records\JobAttempt;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\Types\Str;

use function hash;
use function round;

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
        private LocalIngest $ingest,
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
        $name = $event->job->resolveName();

        $this->ingest->write(new JobAttempt(
            timestamp: $this->executionState->timestamp,
            deploy: $this->executionState->deploy,
            server: $this->executionState->server,
            _group: hash('xxh128', $name),
            trace_id: $this->executionState->trace,
            user: $this->executionState->user->id(),
            job_id: $event->job->uuid(), // @phpstan-ignore argument.type
            attempt_id: $this->executionState->id(),
            attempt: $event->job->attempts(),
            name: $name,
            connection: $event->job->getConnectionName(),
            queue: $this->normalizeQueue($event->job->getConnectionName(), $event->job->getQueue()),
            status: match (true) {
                $event->job->isReleased() => 'released',
                $event->job->hasFailed() => 'failed',
                default => 'processed',
            },
            duration: (int) round(($now - $this->executionState->timestamp) * 1_000_000),
            exceptions: new LazyValue(fn () => $this->executionState->exceptions),
            logs: new LazyValue(fn () => $this->executionState->logs),
            queries: new LazyValue(fn () => $this->executionState->queries),
            lazy_loads: new LazyValue(fn () => $this->executionState->lazyLoads),
            jobs_queued: new LazyValue(fn () => $this->executionState->jobsQueued),
            mail: new LazyValue(fn () => $this->executionState->mail),
            notifications: new LazyValue(fn () => $this->executionState->notifications),
            outgoing_requests: new LazyValue(fn () => $this->executionState->outgoingRequests),
            files_read: new LazyValue(fn () => $this->executionState->filesRead),
            files_written: new LazyValue(fn () => $this->executionState->filesWritten),
            cache_events: new LazyValue(fn () => $this->executionState->cacheEvents),
            hydrated_models: new LazyValue(fn () => $this->executionState->hydratedModels),
            peak_memory_usage: new LazyValue(fn () => $this->executionState->peakMemory()),
            exception_preview: new LazyValue(fn () => Str::tinyText($this->executionState->exceptionPreview)),
        ));
    }
}
