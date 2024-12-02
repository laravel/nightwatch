<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Events\JobPopped;
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
        private CommandState $state,
        private LocalIngest $ingest,
        private Dispatcher $events,
        private ConsoleKernelContract $kernel,
        private Application $app,
    ) {
        //
    }

    public function __invoke(CommandStarting $event): void
    {
        try {
            if ($event->command === 'queue:work') {
                $this->registerJobHooks();
            } else {
                $this->registerCommandHooks();
            }
        } catch (Throwable $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }

    private function registerJobHooks(): void
    {
        $this->state->source = 'job';

        $this->events->listen(JobPopped::class, (new JobPoppedListener($this->state))(...));

        /**
         * @see \Laravel\Nightwatch\Records\JobAttempt
         */
        $this->events->listen(JobAttempted::class, (new JobAttemptedListener($this->sensor, $this->state, $this->ingest))(...));
    }

    private function registerCommandHooks(): void
    {
        /**
         * @see \Laravel\Nightwatch\ExecutionStage::Terminating
         */
        $this->events->listen(CommandFinished::class, (new CommandFinishedListener($this->sensor, $this->state))(...));

        if (! $this->kernel instanceof ConsoleKernel) {
            return;
        }

        // TODO Check this isn't a memory leak in Octane.
        // TODO Check if we can cache this handler between requests on Octane. Same goes for other
        // sub-handlers.
        $this->kernel->whenCommandLifecycleIsLongerThan(-1, new CommandLifecycleIsLongerThanHandler($this->sensor, $this->state, $this->ingest, $this->app));
    }
}
