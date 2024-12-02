<?php

namespace Laravel\Nightwatch\Hooks;

use Carbon\Carbon;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\Contracts\LocalIngest;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\SensorManager;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class CommandLifecycleIsLongerThanHandler
{
    public function __construct(
        private SensorManager $sensor,
        private CommandState $commandState,
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
            // TODO: would caching this locally in a class variable be useful
            // for Octane?
            /** @var LocalIngest */
            $ingest = $this->app->make(LocalIngest::class);

            $ingest->write($this->commandState->records->flush());
        } catch (Throwable $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}

