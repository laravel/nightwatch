<?php

namespace Laravel\Nightwatch\Sensors;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Records\Command;
use Laravel\Nightwatch\State\CommandState;
use RuntimeException;
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
        private CommandState $executionState,
    ) {
        //
    }

    /**
     * TODO this needs to better collect this information, likely via events,
     * as the events give us the normalised values and we can better filter out
     * the `list` command.
     * TODO group
     */
    public function __invoke(InputInterface $input, int $exitCode): void
    {
        if ($this->executionState->artisan && $this->executionState->name) {
            $class = $this->executionState->artisan->get($this->executionState->name)::class;
        }

        $this->executionState->records->write(new Command(
            timestamp: $this->executionState->timestamp,
            deploy: $this->executionState->deploy,
            server: $this->executionState->server,
            _group: hash('sha256', ''),
            trace_id: $this->executionState->trace,
            class: $class ?? '',
            name: $this->executionState->name ?? '',
            command: $input instanceof ArgvInput
                ? implode(' ', $input->getRawTokens())
                : (string) $input,
            // If this value is over 255 or under zero we should run modules 256 on it, e.g.,
            // $exitCode % 256;
            // 3809 % 256 = 225
            // see https://tldp.org/LDP/abs/html/exitcodes.html
            exit_code: $exitCode,
            duration: array_sum($this->executionState->stageDurations),
            bootstrap: $this->executionState->stageDurations[ExecutionStage::Bootstrap->value],
            action: $this->executionState->stageDurations[ExecutionStage::Action->value],
            terminating: $this->executionState->stageDurations[ExecutionStage::Terminating->value],
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
