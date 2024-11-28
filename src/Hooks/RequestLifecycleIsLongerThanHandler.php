<?php

namespace Laravel\Nightwatch\Hooks;

use Carbon\Carbon;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\Contracts\LocalIngest;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\SensorManager;
use Laravel\Nightwatch\State\ExecutionState;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class RequestLifecycleIsLongerThanHandler
{
    public function __construct(
        private SensorManager $sensor,
        private ExecutionState $executionState,
        private Application $app,
    ) {
        //
    }

    public function __invoke(Carbon $startedAt, Request $request, Response $response): void
    {
        try {
            $this->sensor->stage(ExecutionStage::End);
        } catch (Throwable $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }

        try {
            $this->sensor->request($request, $response);
        } catch (Throwable $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }

        try {
            // TODO: would caching this locally in a class variable be useful
            // for Octane?
            /** @var LocalIngest */
            $ingest = $this->app->make(LocalIngest::class);

            $ingest->write($this->executionState->records->flush());
        } catch (Throwable $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
