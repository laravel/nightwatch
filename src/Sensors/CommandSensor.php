<?php

namespace Laravel\Nightwatch\Sensors;

use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Records\Command;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\Supports;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;

use function array_sum;
use function hash;

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

    public function __invoke(InputInterface $input, int $exitCode): void
    {
        $class = $this->executionState->artisan->get($this->executionState->name)::class; // @phpstan-ignore method.nonObject

        /** @var string */
        $name = $this->executionState->name;

        if ($exitCode < 0 || $exitCode > 255) {
            $exitCode = 255;
        }

        $command = match (true) {
            $input instanceof ArgvInput => Supports::parseCommand($input),
            default => (string) $input,
        };

        $this->executionState->records->write(new Command(
            timestamp: $this->executionState->timestamp,
            deploy: $this->executionState->deploy,
            server: $this->executionState->server,
            _group: hash('xxh128', $name),
            trace_id: $this->executionState->trace,
            class: $class,
            name: $name,
            command: $command,
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
