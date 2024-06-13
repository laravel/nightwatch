<?php

namespace Laravel\Nightwatch\Sensors;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\Contracts\PeakMemoryProvider;
use Laravel\Nightwatch\Records\Command;
use Laravel\Nightwatch\Records\ExecutionParent;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;

final class CommandSensor
{
    public function __construct(
        private RecordsBuffer $recordsBuffer,
        private ExecutionParent $executionParent,
        private PeakMemoryProvider $peakMemory,
        private string $traceId,
        private string $deployId,
        private string $server,
    ) {
        //
    }

    public function __invoke(Carbon $startedAt, InputInterface $input, int $status): void
    {
        $duration = round($startedAt->diffInMilliseconds());

        // TODO this needs to better collect this information, likely via events, as the events give us the normalised
        // values and we can better filter out the `list` command.
        $this->recordsBuffer->writeCommand(new Command(
            timestamp: $startedAt->toDateTimeString(),
            deploy_id: $this->deployId,
            server: $this->server,
            group: hash('sha256', ''),  // TODO
            trace_id: $this->traceId,
            user: Auth::id() ?? '', // TODO allow customisation
            name: $input->getFirstArgument(), // TODO
            command: $input instanceof ArgvInput
                ? implode(' ', $input->getRawTokens())
                : (string) $input,
            exit_code: $status,
            duration: $duration,
            queries: $this->executionParent->queries,
            queries_duration: $this->executionParent->queries_duration,
            lazy_loads: $this->executionParent->lazy_loads,
            lazy_loads_duration: $this->executionParent->lazy_loads_duration,
            jobs_queued: $this->executionParent->jobs_queued,
            mail_queued: $this->executionParent->mail_queued,
            mail_sent: $this->executionParent->mail_sent,
            mail_duration: $this->executionParent->mail_duration,
            notifications_queued: $this->executionParent->notifications_queued,
            notifications_sent: $this->executionParent->notifications_sent,
            notifications_duration: $this->executionParent->notifications_duration,
            outgoing_requests: $this->executionParent->outgoing_requests,
            outgoing_requests_duration: $this->executionParent->outgoing_requests_duration,
            files_read: $this->executionParent->files_read,
            files_read_duration: $this->executionParent->files_read_duration,
            files_written: $this->executionParent->files_written,
            files_written_duration: $this->executionParent->files_written_duration,
            cache_hits: $this->executionParent->cache_hits,
            cache_misses: $this->executionParent->cache_misses,
            hydrated_models: $this->executionParent->hydrated_models,
            peak_memory_usage_kilobytes: $this->peakMemory->kilobytes(),
        ));
    }
}
