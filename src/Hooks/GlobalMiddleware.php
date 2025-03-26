<?php

namespace Laravel\Nightwatch\Hooks;

use Closure;
use Illuminate\Http\Request;
use Laravel\Nightwatch\Compatibility;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Laravel\Nightwatch\Types\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * @internal
 */
final class GlobalMiddleware
{
    private bool $hasRun = false;

    /**
     * @param  Core<RequestState|CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function handle(Request $request, Closure $next): mixed
    {
        try {
            $this->nightwatch->state->executionPreview = Str::tinyText(
                $request->getMethod().' '.$request->getBaseUrl().$request->getPathInfo()
            );
        } catch (Throwable $e) {
            $this->nightwatch->report($e);
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (Compatibility::$terminatingEventExists) {
            return;
        }

        if ($this->hasRun) {
            return;
        }

        $this->hasRun = true;

        try {
            $this->nightwatch->sensor->stage(ExecutionStage::Terminating);
        } catch (Throwable $e) {
            $this->nightwatch->report($e);
        }
    }
}
