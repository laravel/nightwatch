<?php

namespace Laravel\Nightwatch\Hooks;

use Carbon\Carbon;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\Contracts\LocalIngest;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\SensorManager;
use Laravel\Nightwatch\State\CommandState;
use Symfony\Component\Console\Input\InputInterface;
use Throwable;

final class CommandLifecycleIsLongerThanHandler
{
    public function __construct(
        private SensorManager $sensor,
        private CommandState $state,
        private Application $app,
    ) {
        //
    }

    public function __invoke(Carbon $startedAt, InputInterface $input, int $exitCode): void
    {
        try {
            if (! $this->app->runningInConsole()) {
                return;
            }

            $this->sensor->stage(ExecutionStage::End);
            $this->sensor->command($input, $exitCode);

            /** @var LocalIngest */
            $ingest = $this->app->make(LocalIngest::class);
            $ingest->write($this->state->records->flush());
        } catch (Throwable $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
