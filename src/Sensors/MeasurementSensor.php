<?php

namespace Laravel\Nightwatch\Sensors;

use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Laravel\Nightwatch\Types\Str;

use function hash;
use function round;

/**
 * @internal
 */
final class MeasurementSensor
{
    public function __construct(
        private RequestState|CommandState $executionState,
    ) {
        //
    }

    /**
     * @return array<mixed>
     */
    public function __invoke(
        string $name,
        float $startMicrotime,
        float $endMicrotime,
        string $file,
        int $line
    ): array {
        $this->executionState->measurements++;

        return [
            'v' => 1,
            't' => 'measurement',
            'timestamp' => $startMicrotime,
            'deploy' => $this->executionState->deploy,
            'server' => $this->executionState->server,
            '_group' => hash('xxh128', $name),
            'trace_id' => $this->executionState->trace,
            'execution_source' => $this->executionState->source,
            'execution_id' => $this->executionState->id(),
            'execution_preview' => $this->executionState->executionPreview(),
            'execution_stage' => $this->executionState->stage,
            'user' => $this->executionState->user->id(),
            // --- //
            'name' => Str::tinyText($name),
            'file' => Str::tinyText($file),
            'line' => $line,
            'duration' => (int) round(($endMicrotime - $startMicrotime) * 1_000_000),
        ];
    }
}
