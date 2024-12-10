<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobPopping;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\Contracts\LocalIngest;
use Laravel\Nightwatch\SensorManager;
use Laravel\Nightwatch\State\CommandState;
use Throwable;

/**
 * @internal
 */
final class CommandStartingListener
{
    public function __construct(
        private SensorManager $sensor,
        private CommandState $executionState,
        private LocalIngest $ingest,
        private Dispatcher $events,
        private ConsoleKernelContract $kernel,
    ) {
        //
    }

    public function __invoke(CommandStarting $event): void
    {
        try {
            match ($event->command) {
                'queue:work', 'queue:listen' => $this->registerJobHooks(),
                'schedule:run', 'schedule:work' => $this->registerScheduledTaskHooks(),
                default => $this->registerCommandHooks(),
            };
        } catch (Throwable $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }

    private function registerJobHooks(): void
    {
        $this->executionState->source = 'job';

        $this->events->listen(JobPopping::class, (new JobPoppingListener($this->executionState))(...));

        $this->events->listen(JobProcessing::class, (new JobProcessingListener($this->executionState))(...));

        /**
         * @see \Laravel\Nightwatch\Records\JobAttempt
         */
        $this->events->listen(JobAttempted::class, (new JobAttemptedListener($this->sensor, $this->executionState, $this->ingest))(...));

        /**
         * @see \Laravel\Nightwatch\Records\Exception
         */
        $this->events->listen(JobExceptionOccurred::class, (new JobExceptionOccurredListener($this->sensor))(...));
    }

    private function registerCommandHooks(): void
    {
        /**
         * @see \Laravel\Nightwatch\ExecutionStage::Terminating
         */
        $this->events->listen(CommandFinished::class, (new CommandFinishedListener($this->sensor, $this->executionState))(...));

        if (! $this->kernel instanceof ConsoleKernel) {
            return;
        }

        // TODO Check this isn't a memory leak in Octane.
        // TODO Check if we can cache this handler between requests on Octane. Same goes for other
        // sub-handlers.
        $this->kernel->whenCommandLifecycleIsLongerThan(-1, new CommandLifecycleIsLongerThanHandler($this->sensor, $this->executionState, $this->ingest));
    }

    private function registerScheduledTaskHooks(): void
    {
        $this->events->listen(ScheduledTaskStarting::class, (new ScheduledTaskStartingListener($this->executionState))(...));

        $this->events->listen([
            ScheduledTaskFinished::class,
            ScheduledTaskSkipped::class,
            ScheduledTaskFailed::class,
        ], (new ScheduledTaskListener($this->sensor, $this->executionState, $this->ingest))(...));
    }
}
