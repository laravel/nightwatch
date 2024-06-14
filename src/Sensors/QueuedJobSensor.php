<?php

namespace Laravel\Nightwatch\Sensors;

use Laravel\Nightwatch\Records\ExecutionParent;

class QueuedJobSensor
{
    public function __construct(
        private ExecutionParent $executionParent,
    ) {
        //
    }
    public function __invoke()
    {
        $this->executionParent->jobs_queued++;
    }
}
