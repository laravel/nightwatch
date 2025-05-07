<?php

namespace Laravel\Nightwatch\Hooks;

use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\CommandState;

/**
 * @internal
 */
final class WorkerEventListener
{
    /**
     * @param  Core<CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(): void
    {
        $this->nightwatch->ingest();
    }
}
