<?php

namespace Laravel\Nightwatch\Sensors;

use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\State\ExecutionState;

use function round;

final class StageSensor
{
    public function __construct(
        private Clock $clock,
        private ExecutionState $executionState,
    ) {
        //
    }

    public function __invoke(ExecutionStage $executionStage): void
    {
        $nowMicrotime = $this->clock->microtime();

        $this->executionState->stageDurations[$this->executionState->stage->value] = (int) round(($nowMicrotime - $this->executionState->currentExecutionStageStartedAtMicrotime) * 1_000_000);
        $this->executionState->stage = $executionStage;
        $this->executionState->currentExecutionStageStartedAtMicrotime = $nowMicrotime;
    }
}
