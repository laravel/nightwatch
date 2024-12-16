<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Contracts\Console\Kernel as KernelContract;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Console\Kernel;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\SensorManager;
use Laravel\Nightwatch\State\CommandState;
use Throwable;

final class ConsoleKernelResolvedHandler
{
    public function __construct(private SensorManager $sensor, private CommandState $commandState)
    {
        //
    }

    public function __invoke(KernelContract $kernel, Application $app): void
    {
        try {
            if (! $kernel instanceof Kernel) {
                return;
            }

            // TODO Check this isn't a memory leak in Octane.
            // TODO Check if we can cache this handler between requests on Octane. Same goes for other
            // sub-handlers.
            $kernel->whenCommandLifecycleIsLongerThan(-1, new CommandLifecycleIsLongerThanHandler($this->sensor, $this->commandState, $app));
        } catch (Throwable $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
