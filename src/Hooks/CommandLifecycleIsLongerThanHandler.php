<?php

namespace Laravel\Nightwatch\Hooks;

use Carbon\Carbon;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\State\CommandState;
use Symfony\Component\Console\Input\InputInterface;
use Throwable;

/**
 * @internal
 */
final class CommandLifecycleIsLongerThanHandler
{
    /**
     * @param  Core<CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(Carbon $startedAt, InputInterface $input, int $status): void
    {
        try {
            $this->nightwatch->sensor->stage(ExecutionStage::End);
        } catch (Throwable $e) {
            $this->nightwatch->report($e);
        }

        try {
            $this->nightwatch->sensor->command($input, $status);
        } catch (Throwable $e) {
            $this->nightwatch->report($e);
        }

        $this->nightwatch->ingest();
    }
}
