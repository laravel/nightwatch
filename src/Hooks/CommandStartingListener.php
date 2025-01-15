<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobPopping;
use Illuminate\Queue\Events\JobProcessing;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\CommandState;
use RuntimeException;
use Throwable;

use function in_array;

/**
 * @internal
 */
final class CommandStartingListener
{
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
        try {
            if ($this->nightwatch->state->name === null) {
                $this->nightwatch->state->name = $event->command;
            } else {
                return;
            }
        } catch (Throwable $e) { // @phpstan-ignore catch.neverThrown
            $this->nightwatch->report($e);
        }

        try {
            if (in_array($event->command, ['queue:work', 'queue:listen'], true)) {
                $this->registerJobHooks();
            } else {
                $this->registerCommandHooks();
            }
        } catch (Throwable $e) {
            $this->nightwatch->report($e);
        }
    }

    private function registerJobHooks(): void
    {
        $this->nightwatch->state->source = 'job';

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
         * @see \Laravel\Nightwatch\Records\JobAttempt
         */
        $this->events->listen(JobAttempted::class, (new JobAttemptedListener($this->nightwatch))(...));

        /**
         * @see \Laravel\Nightwatch\Records\Exception
         */
        $this->events->listen(JobExceptionOccurred::class, (new JobExceptionOccurredListener($this->nightwatch))(...));
    }

    private function registerCommandHooks(): void
    {
        if (! $this->kernel instanceof ConsoleKernel) {
            $this->nightwatch->handleUnrecoverableException(
                new RuntimeException('Command capturing is not possible when using a custom Console Kernel implementation.')
            );

            return;
        }

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
