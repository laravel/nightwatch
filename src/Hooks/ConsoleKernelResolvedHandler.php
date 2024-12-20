<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Contracts\Console\Kernel as KernelContract;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Console\Kernel;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\CommandState;
use Throwable;

final class ConsoleKernelResolvedHandler
{
    /**
     * @param  Core<CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(KernelContract $kernel, Application $app): void
    {
        try {
            if ($kernel instanceof Kernel) {
                // TODO Check this isn't a memory leak in Octane.
                // TODO Check if we can cache this handler between requests on Octane. Same goes for other
                // sub-handlers.
                $kernel->whenCommandLifecycleIsLongerThan(-1, new CommandLifecycleIsLongerThanHandler($this->nightwatch, $app));
            }
        } catch (Throwable $e) {
            $this->nightwatch->handleUnrecoverableException($e);
        }
    }
}
