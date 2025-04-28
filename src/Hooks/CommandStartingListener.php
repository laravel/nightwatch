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
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\State\CommandState;
use Throwable;

/**
 * @internal
 */
final class CommandStartingListener
{
    private bool $hasRun = false;

    /**
     * @param  Core<CommandState>  $nightwatch
     */
    public function __construct(
        private Dispatcher $events,
        private Core $nightwatch,
        private ConsoleKernelContract $kernel,
    ) {
        //
    }

    public function __invoke(CommandStarting $event): void
    {
        if ($this->hasRun) {
            return;
        }

        $this->hasRun = true;

        try {
            match ($event->command) {
                'queue:work', 'queue:listen', 'horizon:work' => $this->registerJobHooks(),
                'schedule:run', 'schedule:work' => $this->registerScheduledTaskHooks(),
                default => $this->registerCommandHooks($event),
            };
        } catch (Throwable $e) {
            Nightwatch::unrecoverableExceptionOccurred($e);
        }
    }

    private function registerJobHooks(): void
    {
        $this->nightwatch->configureForJobs();

        /**
         * @see \Laravel\Nightwatch\State\CommandState::reset()
         */
        $this->events->listen(JobPopping::class, (new JobPoppingListener($this->nightwatch))(...));

        /**
         * @see \Laravel\Nightwatch\State\CommandState::$timestamp
         * @see \Laravel\Nightwatch\State\CommandState::$id
         */
        $this->events->listen(JobProcessing::class, (new JobProcessingListener($this->nightwatch))(...));

        /**
         * @see \Laravel\Nightwatch\Records\Exception
         */
        $this->events->listen(JobExceptionOccurred::class, (new JobExceptionOccurredListener($this->nightwatch))(...));

        /**
         * @see \Laravel\Nightwatch\Records\JobAttempt
         * @see \Laravel\Nightwatch\Core::ingest()
         */
        $this->events->listen(JobAttempted::class, (new JobAttemptedListener($this->nightwatch))(...));
    }

    private function registerScheduledTaskHooks(): void
    {
        $this->nightwatch->configureForScheduledTasks();

        $this->events->listen(ScheduledTaskStarting::class, (new ScheduledTaskStartingListener($this->nightwatch))(...));

        /**
         * @see \Laravel\Nightwatch\Core::ingest()
         */
        $this->events->listen([
            ScheduledTaskFinished::class,
            ScheduledTaskSkipped::class,
            ScheduledTaskFailed::class,
        ], (new ScheduledTaskListener($this->nightwatch))(...));
    }

    private function registerCommandHooks(CommandStarting $event): void
    {
        if (! $this->kernel instanceof ConsoleKernel) {
            return;
        }

        try {
            $this->nightwatch->configureSampling('commands');
        } catch (Throwable $e) {
            $this->nightwatch->shouldSample = false;

            throw $e;
        }

        $this->nightwatch->prepareForCommand($event->command);

        /**
         * @see \Laravel\Nightwatch\ExecutionStage::Terminating
         */
        $this->events->listen(CommandFinished::class, (new CommandFinishedListener($this->nightwatch))(...));

        /**
         * @see \Laravel\Nightwatch\ExecutionStage::End
         * @see \Laravel\Nightwatch\Records\Command
         * @see \Laravel\Nightwatch\Core::ingest()
         *
         * TODO Check this isn't a memory leak in Octane.
         * TODO Check if we can cache this handler between requests on Octane. Same goes for other
         * sub-handlers.
         */
        $this->kernel->whenCommandLifecycleIsLongerThan(-1, new CommandLifecycleIsLongerThanHandler($this->nightwatch));
    }
}
