<?php

namespace Laravel\Nightwatch\Hooks;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\State\RequestState;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * @internal
 */
final class RequestLifecycleIsLongerThanHandler
{
    /**
     * @param  Core<RequestState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(Carbon $startedAt, Request $request, Response $response): void
    {
        try {
            $this->nightwatch->sensor->stage(ExecutionStage::End);
        } catch (Throwable $e) {
            $this->nightwatch->report($e);
        }

        try {
            $this->nightwatch->sensor->user();
        } catch (Throwable $e) {
            $this->nightwatch->report($e);
        }

        try {
            $this->nightwatch->sensor->request($request, $response);
        } catch (Throwable $e) {
            $this->nightwatch->report($e);
        }

        $this->nightwatch->ingest();
    }
}
