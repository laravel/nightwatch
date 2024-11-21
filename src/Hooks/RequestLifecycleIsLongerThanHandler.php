<?php

namespace Laravel\Nightwatch\Hooks;

use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\Contracts\LocalIngest;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\SensorManager;
use Symfony\Component\HttpFoundation\Response;

final class RequestLifecycleIsLongerThanHandler
{
    public function __construct(
        private SensorManager $sensor,
        private Application $app,
    ) {
        //
    }

    public function __invoke(Carbon $startedAt, Request $request, Response $response): void
    {
        try {
            $this->sensor->stage(ExecutionStage::End);
        } catch (Exception $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }

        try {
            $this->sensor->request($request, $response);
        } catch (Exception $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }

        try {
            // TODO: would caching this locally in a class variable be useful
            // for Octane?
            /** @var LocalIngest */
            $ingest = $this->app->make(LocalIngest::class);

            $ingest->write($this->sensor->flush());
        } catch (Exception $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
