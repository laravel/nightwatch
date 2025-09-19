<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel as KernelContract;
use Illuminate\Foundation\Events\Terminating;
use Illuminate\Foundation\Http\Kernel;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Http\Middleware\Sample;
use Laravel\Nightwatch\State\RequestState;
use Throwable;

/**
 * @internal
 */
final class HttpKernelResolvedHandler
{
    /**
     * @param  Core<RequestState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(KernelContract $kernel, Application $app): void
    {
        if (! $kernel instanceof Kernel) {
            return;
        }

        try {
            /**
             * @see \Laravel\Nightwatch\ExecutionStage::End
             * @see \Laravel\Nightwatch\Records\Request
             * @see \Laravel\Nightwatch\Core::finishExecution()
             */
            $kernel->whenRequestLifecycleIsLongerThan(-1, new RequestLifecycleIsLongerThanHandler($this->nightwatch));
        } catch (Throwable $e) {
            Nightwatch::unrecoverableExceptionOccurred($e);
        }

        try {
            /**
             * @see \Laravel\Nightwatch\ExecutionStage::Terminating
             */
            $kernel->prependMiddleware(GlobalMiddleware::class);
        } catch (Throwable $e) {
            $this->nightwatch->report($e, handled: true);
        }

        try {
            $kernel->prependToMiddlewarePriority(Sample::class);
        } catch (Throwable $e) {
            $this->nightwatch->report($e);
        }
    }
}
