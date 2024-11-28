<?php

namespace Laravel\Nightwatch\Sensors;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Records\Command;
use Laravel\Nightwatch\State\ExecutionState;
use RuntimeException;
use Symfony\Component\Console\Input\ArgvInput;

use function hash;
use function implode;
use function round;

/**
 * @internal
 */
final class CommandSensor
{
    private ?float $startTime = null;

    public function __construct(
        private Clock $clock,
        private ExecutionState $executionState,
    ) {
        //
    }

    /**
     * TODO this needs to better collect this information, likely via events,
     * as the events give us the normalised values and we can better filter out
     * the `list` command.
     * TODO group
     */
    public function __invoke(CommandStarting|CommandFinished $event): void
    {
        $now = $this->clock->microtime();

        if ($event instanceof CommandStarting) {
            $this->startTime = $now;

            return;
        }

        if ($this->startTime === null) {
            throw new RuntimeException('No start time found for ['.$event::class."] event for command [{$event->command}].");
        }

        $this->executionState->records->write(new Command(
            timestamp: $this->startTime,
            deploy: $this->executionState->deploy,
            server: $this->executionState->server,
            _group: hash('sha256', ''),
            trace_id: $this->executionState->trace,
            class: '',
            name: $event->command,
            command: $event->input instanceof ArgvInput
                ? implode(' ', $event->input->getRawTokens())
                : (string) $event->input,
            // If this value is over 255 or under zero we should run modules 256 on it, e.g.,
            // $exitCode % 256;
            // 3809 % 256 = 225
            // see https://tldp.org/LDP/abs/html/exitcodes.html
            exit_code: $event->exitCode,
            duration: (int) round(($now - $this->startTime) * 1_000_000),
            bootstrap: 0,
            action: 0,
            terminating: 0,
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
