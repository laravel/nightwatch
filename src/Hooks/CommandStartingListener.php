<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobPopped;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Illuminate\Support\Facades\Log;
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
        $this->events->listen([
            JobProcessed::class,
            JobReleasedAfterException::class,
            JobFailed::class
        ], (new JobAttemptListener($this->sensor))(...));
    }

    private function registerCommandHooks(): void
    {
        // TODO: Set terminating when event doesn't exist

        $this->events->listen(CommandFinished::class, function ($event) {
            $this->state->name = $event->command;
        });


        if (! $this->kernel instanceof ConsoleKernel) {
            return;
        }

        $this->kernel->whenCommandLifecycleIsLongerThan(-1, new CommandLifecycleIsLongerThanHandler($this->sensor, $this->state, $this->app));
    }
}
