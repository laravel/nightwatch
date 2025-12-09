<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Console\Events\ScheduledTaskStarting;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\CommandState;
use Throwable;

use function in_array;

/**
 * @internal
 */
final class ScheduledTaskStartingListener
{
    /**
     * @param  Core<CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(ScheduledTaskStarting $event): void
    {
        try {
            $this->nightwatch->prepareForNextScheduledTask();

            if (in_array($event->task->command, $this->nightwatch->defaultVendorCommands(), true)) {
                $this->nightwatch->dontSample();
            }
        } catch (Throwable $e) {
            $this->nightwatch->report($e, handled: true);
        }
    }
}
