<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\State\CommandState;
use Throwable;

/**
 * @internal
 */
final class JobProcessingListener
{
    public function __construct(private CommandState $executionState)
    {
        //
    }

    public function __invoke(JobProcessing $event): void
    {
        try {
            $this->executionState->resetTimestamp();
        } catch (Throwable $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
