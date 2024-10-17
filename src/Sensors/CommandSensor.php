<?php

namespace Laravel\Nightwatch\Sensors;

use Carbon\Carbon;
use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\Contracts\PeakMemoryProvider;
use Laravel\Nightwatch\Records\Command;
use Laravel\Nightwatch\Records\ExecutionState;
use Laravel\Nightwatch\UserProvider;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;

use function hash;
use function implode;
use function round;

/**
 * @internal
 */
final class CommandSensor
{
    public function __construct(
        private RecordsBuffer $recordsBuffer,
        private ExecutionState $executionState,
        private PeakMemoryProvider $peakMemory,
        private UserProvider $user,
        private string $traceId,
        private string $server,
    ) {
        //
    }

    /**
     * TODO this needs to better collect this information, likely via events,
     * as the events give us the normalised values and we can better filter out
     * the `list` command.
     * TODO group
     */
    public function __invoke(Carbon $startedAt, InputInterface $input, int $status): void
    {
        $durationInMilliseconds = (int) round($startedAt->diffInMilliseconds());

        $this->recordsBuffer->writeCommand(new Command(
            timestamp: $startedAt->getTimestamp(),
            deploy: $this->executionState->deploy,
            server: $this->server,
            group: hash('sha256', ''),
            trace_id: $this->traceId,
            user: $this->user->id(),
            name: $input->getFirstArgument() ?? 'list',
            command: $input instanceof ArgvInput
                ? implode(' ', $input->getRawTokens())
                : (string) $input,
            // If this value is over 255 or under zero we should run modules 256 on it, e.g.,
            // $exitCode % 256;
            // 3809 % 256 = 225
            // see https://tldp.org/LDP/abs/html/exitcodes.html
            exit_code: $status,
            duration: $durationInMilliseconds,
            queries: $this->executionState->queries,
            queries_duration: $this->executionState->queries_duration,
            lazy_loads: $this->executionState->lazy_loads,
            lazy_loads_duration: $this->executionState->lazy_loads_duration,
            jobs_queued: $this->executionState->jobs_queued,
            mail_queued: $this->executionState->mail_queued,
            mail_sent: $this->executionState->mail_sent,
            mail_duration: $this->executionState->mail_duration,
            notifications_queued: $this->executionState->notifications_queued,
            notifications_sent: $this->executionState->notifications_sent,
            notifications_duration: $this->executionState->notifications_duration,
            outgoing_requests: $this->executionState->outgoing_requests,
            outgoing_requests_duration: $this->executionState->outgoing_requests_duration,
            files_read: $this->executionState->files_read,
            files_read_duration: $this->executionState->files_read_duration,
            files_written: $this->executionState->files_written,
            files_written_duration: $this->executionState->files_written_duration,
            cache_hits: $this->executionState->cache_hits,
            cache_misses: $this->executionState->cache_misses,
            cache_writes: $this->executionState->cache_writes,
            hydrated_models: $this->executionState->hydrated_models,
            peak_memory_usage: $this->peakMemory->kilobytes(),
        ));
    }
}
