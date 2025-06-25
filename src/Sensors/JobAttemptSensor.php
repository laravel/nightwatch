<?php

namespace Laravel\Nightwatch\Sensors;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Concerns\NormalizesQueue;
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
        private CommandState $commandState,
        private Clock $clock,
        private array $connectionConfig,
    ) {
        //
    }

    /**
     * @return ?array{0: JobAttempt, 1: callable(): array<mixed>}
     */
    public function __invoke(JobProcessed|JobReleasedAfterException|JobFailed $event): ?array
    {
        if ($event->connectionName === 'sync') {
            return null;
        }

        $now = $this->clock->microtime();
        $name = $event->job->resolveName();

        return [
            $record = new JobAttempt(
                jobId: $event->job->uuid(), // @phpstan-ignore argument.type
                attemptId: $this->commandState->id()->resolve(),
                attempt: $event->job->attempts(),
                name: $name,
                connection: $event->job->getConnectionName(),
                queue: $this->normalizeQueue($event->job->getConnectionName(), $event->job->getQueue()),
                status: match (true) {
                    $event->job->isReleased() => 'released',
                    $event->job->hasFailed() => 'failed',
                    default => 'processed',
                },
                duration: (int) round(($now - $this->commandState->timestamp) * 1_000_000),
            ),
            function () use ($record) {
                return [
                    'v' => 1,
                    't' => 'job-attempt',
                    'timestamp' => $this->commandState->timestamp,
                    'deploy' => $this->commandState->deploy,
                    'server' => $this->commandState->server,
                    '_group' => hash('xxh128', $record->name),
                    'trace_id' => $this->commandState->trace,
                    'user' => $this->commandState->user->id(),
                    // --- //
                    'job_id' => $record->jobId,
                    'attempt_id' => $record->attemptId,
                    'attempt' => $record->attempt,
                    'name' => Str::text($record->name),
                    'connection' => Str::tinyText($record->connection),
                    'queue' => Str::tinyText($record->queue),
                    'status' => $record->status,
                    'duration' => $record->duration,
                    // --- //
                    'exceptions' => new LazyValue(fn () => $this->commandState->exceptions),
                    'logs' => new LazyValue(fn () => $this->commandState->logs),
                    'queries' => new LazyValue(fn () => $this->commandState->queries),
                    'lazy_loads' => new LazyValue(fn () => $this->commandState->lazyLoads),
                    'jobs_queued' => new LazyValue(fn () => $this->commandState->jobsQueued),
                    'mail' => new LazyValue(fn () => $this->commandState->mail),
                    'notifications' => new LazyValue(fn () => $this->commandState->notifications),
                    'outgoing_requests' => new LazyValue(fn () => $this->commandState->outgoingRequests),
                    'files_read' => new LazyValue(fn () => $this->commandState->filesRead),
                    'files_written' => new LazyValue(fn () => $this->commandState->filesWritten),
                    'cache_events' => new LazyValue(fn () => $this->commandState->cacheEvents),
                    'hydrated_models' => new LazyValue(fn () => $this->commandState->hydratedModels),
                    'peak_memory_usage' => new LazyValue(fn () => $this->commandState->peakMemory()),
                    'exception_preview' => new LazyValue(fn () => Str::tinyText($this->commandState->exceptionPreview)),
                ];
            },
        ];
    }
}
