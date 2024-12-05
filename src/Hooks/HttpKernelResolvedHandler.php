<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel as KernelContract;
use Illuminate\Foundation\Events\Terminating;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\SensorManager;
use Laravel\Nightwatch\State\RequestState;
use Throwable;

use function class_exists;

final class HttpKernelResolvedHandler
{
    public function __construct(private SensorManager $sensor, private RequestState $requestState)
    {
        //
    }

    public function __invoke(KernelContract $kernel, Application $app): void
    {
        try {
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
            $kernel->whenRequestLifecycleIsLongerThan(-1, new RequestLifecycleIsLongerThanHandler($this->sensor, $this->requestState, $app));
        } catch (Throwable $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
