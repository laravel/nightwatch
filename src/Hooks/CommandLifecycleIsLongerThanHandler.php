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
        private CommandState $commandState,
        private LocalIngest $ingest,
        private Application $app,
    ) {
        //
    }

    public function __invoke(Carbon $startedAt, InputInterface $input, int $status): void
    {
        try {
            $this->sensor->stage(ExecutionStage::End);
        } catch (Throwable $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }

        try {
            $this->sensor->command($input, $status);
        } catch (Throwable $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }

        try {
            $this->ingest->write($this->commandState->records->flush());
        } catch (Throwable $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
