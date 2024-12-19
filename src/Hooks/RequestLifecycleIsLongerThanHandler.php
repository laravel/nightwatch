<?php

namespace Laravel\Nightwatch\Hooks;

use Carbon\Carbon;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Laravel\Nightwatch\Contracts\LocalIngest;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\State\RequestState;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class RequestLifecycleIsLongerThanHandler
{
    /**
     * @param  Core<RequestState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
        private Application $app,
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
            $this->nightwatch->sensor->request($request, $response);
        } catch (Throwable $e) {
            $this->nightwatch->report($e);
        }

        try {
            // TODO: would caching this locally in a class variable be useful
            // for Octane?
            /** @var LocalIngest */
            $ingest = $this->app->make(LocalIngest::class);

            $ingest->write($this->nightwatch->state->records->flush());
        } catch (Throwable $e) {
            // HANDLE THIS!
        }
    }
}
