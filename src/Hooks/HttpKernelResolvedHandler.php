<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel as KernelContract;
use Illuminate\Foundation\Events\Terminating;
use Illuminate\Foundation\Http\Kernel;
use Laravel\Nightwatch\SensorManager;

use function class_exists;

class HttpKernelResolvedHandler
{
    public function __construct(private SensorManager $sensor)
    {
        //
    }

    public function __invoke(KernelContract $kernel, Application $app): void
    {
        if (! $kernel instanceof Kernel) {
            return;
        }

        if (! class_exists(Terminating::class)) {
            $kernel->setGlobalMiddleware([
                TerminatingMiddleware::class, // TODO Check this isn't a memory leak in Octane.
                ...$kernel->getGlobalMiddleware(),
            ]);
        }

        // TODO Check this isn't a memory leak in Octane.
        // TODO Check if we can cache this handler between requests on Octane. Same goes for other
        // sub-handlers.
        $kernel->whenRequestLifecycleIsLongerThan(-1, new RequestLifecycleIsLongerThanHandler($this->sensor, $app));
    }
}
