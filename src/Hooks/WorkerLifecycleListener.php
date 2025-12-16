<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Queue\Events\JobPopping;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Events\WorkerStopping;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\CommandState;
use Throwable;

/**
 * @internal
 */
final class WorkerLifecycleListener
{
    /**
     * @param  Core<CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(Looping|JobPopping|JobProcessing|WorkerStopping|CommandFinished $event): void
    {
        try {
            match ($event::class) {
                Looping::class, WorkerStopping::class => $this->nightwatch->finishExecution()->waitForExecution(),
                CommandFinished::class => $event->command === 'queue:work' && $this->nightwatch->finishExecution()->waitForExecution(),
                JobPopping::class => $this->nightwatch->prepareForNextJob(),
                JobProcessing::class => $this->nightwatch->prepareForJob($event->job),
            };
        } catch (Throwable $e) {
            $this->nightwatch->report($e, handled: true);
        }
    }
}
