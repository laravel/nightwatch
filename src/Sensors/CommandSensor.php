<?php

namespace Laravel\Nightwatch\Sensors;

use Laravel\Nightwatch\Compatibility;
use Laravel\Nightwatch\Concerns\RecordsContext;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Records\Command;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\Types\Str;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;

use function array_sum;
use function hash;

/**
 * @internal
 */
final class CommandSensor
{
    use RecordsContext;

    public function __construct(
        private CommandState $commandState,
    ) {
        //
    }

    /**
     * @return array{0: Command, 1: callable(): array<mixed>}
     */
    public function __invoke(InputInterface $input, int $exitCode): array
    {
        $class = $this->commandState->artisan->get($this->commandState->name)::class; // @phpstan-ignore method.nonObject

        /** @var string */
        $name = $this->commandState->name;

        if ($exitCode < 0 || $exitCode > 255) {
            $exitCode = 255;
        }

        $command = match (true) {
            $input instanceof ArgvInput => Compatibility::parseCommand($input),
            default => (string) $input,
        };

        return [
            $record = new Command(
                class: $class,
                name: $name,
                command: $command,
                exitCode: $exitCode,
                duration: array_sum($this->commandState->stageDurations),
            ),
            function () use ($record) {
                return [
                    'v' => 1,
                    't' => 'command',
                    'timestamp' => $this->commandState->timestamp,
                    'deploy' => $this->commandState->deploy,
                    'server' => $this->commandState->server,
                    '_group' => hash('xxh128', $record->name),
                    'trace_id' => $this->commandState->trace,
                    // --- //
                    'class' => $record->class,
                    'name' => $record->name,
                    'command' => $record->command,
                    'exit_code' => $record->exitCode,
                    'duration' => $record->duration,
                    // --- //
                    'bootstrap' => $this->commandState->stageDurations[ExecutionStage::Bootstrap->value],
                    'action' => $this->commandState->stageDurations[ExecutionStage::Action->value],
                    'terminating' => $this->commandState->stageDurations[ExecutionStage::Terminating->value],
                    'exceptions' => $this->commandState->exceptions,
                    'logs' => $this->commandState->logs,
                    'queries' => $this->commandState->queries,
                    'lazy_loads' => $this->commandState->lazyLoads,
                    'jobs_queued' => $this->commandState->jobsQueued,
                    'mail' => $this->commandState->mail,
                    'notifications' => $this->commandState->notifications,
                    'outgoing_requests' => $this->commandState->outgoingRequests,
                    'files_read' => $this->commandState->filesRead,
                    'files_written' => $this->commandState->filesWritten,
                    'cache_events' => $this->commandState->cacheEvents,
                    'hydrated_models' => $this->commandState->hydratedModels,
                    'peak_memory_usage' => $this->commandState->peakMemory(),
                    'exception_preview' => Str::tinyText($this->commandState->exceptionPreview),
                    'context' => $this->serializedContext(),
                ];
            },
        ];
    }
}
