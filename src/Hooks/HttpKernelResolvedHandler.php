<?php

namespace Laravel\Nightwatch\Hooks;

use Carbon\Carbon;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel as KernelContract;
use Illuminate\Foundation\Events\Terminating;
use Illuminate\Http\Request;
use Laravel\Nightwatch\SensorManager;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernel;
use Illuminate\Foundation\Http\Kernel;

class HttpKernelResolvedHandler
{
    public function __construct(private SensorManager $sensor)
    {
        //
    }

    public function __invoke(KernelContract $kernel, Application $app)
    {
        if (! $kernel instanceof Kernel) {
            return;
        }

        if (! class_exists(Terminating::class)) {
            $kernel->setGlobalMiddleware([
                TerminatingMiddleware::class, // Check this isn't a memory leak in Octane
                ...$kernel->getGlobalMiddleware(),
            ]);
        }

        $kernel->whenRequestLifecycleIsLongerThan(-1, new RequestLifecycleIsLongerThanHandler($this->sensor, $app));
    }
}
