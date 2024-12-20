<?php

namespace Laravel\Nightwatch\Hooks;

use Carbon\Carbon;
use Illuminate\Contracts\Foundation\Application;
use Laravel\Nightwatch\Contracts\LocalIngest;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\State\CommandState;
use Symfony\Component\Console\Input\InputInterface;
use Throwable;

final class CommandLifecycleIsLongerThanHandler
{
    /**
     * @param  Core<CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
        private Application $app,
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

        try {
            // TODO: would caching this locally in a class variable be useful
            // for Octane?
            /** @var LocalIngest */
            $ingest = $this->app->make(LocalIngest::class);

            $ingest->write($this->nightwatch->state->records->flush());
        } catch (Throwable $e) {
            $this->nightwatch->handleUnrecoverableException($e);
        }
    }
}
