<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Console\Events\ScheduledTaskStarting;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\CommandState;
use Throwable;

use function sprintf;

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
        $this->configureSamplingForEvent($event);

        try {
            $this->nightwatch->prepareForNextScheduledTask();
        } catch (Throwable $e) {
            $this->nightwatch->report($e, handled: true);
        }
    }

    protected function configureSamplingForEvent(ScheduledTaskStarting $event): void
    {
        $sampling = $this->nightwatch->sampling();

        $event->task->command = sprintf('NIGHTWATCH_SCHEDULE_TASK_SUBCOMMAND_SAMPLED=%d %s', $sampling, $event->task->command);
    }
}
