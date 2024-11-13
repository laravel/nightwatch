<?php

namespace Laravel\Nightwatch\Hooks;

use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Laravel\Nightwatch\Contracts\LocalIngest;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\SensorManager;
use Symfony\Component\HttpFoundation\Response;

class RequestLifecycleIsLongerThanHandler
{
    public function __construct(
        private SensorManager $sensor,
        private Application $app,
    ) {
        //
    }

    public function __invoke(Carbon $startedAt, Request $request, Response $response)
    {
        try {
            $this->sensor->stage(ExecutionStage::End);

            $this->sensor->request($request, $response);

            /** @var LocalIngest */
            $ingest = $this->app->make(LocalIngest::class);

            $ingest->write($this->sensor->flush());
        } catch (Exception $e) {
            //
        }
    }
}
