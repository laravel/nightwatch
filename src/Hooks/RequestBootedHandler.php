<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Contracts\Foundation\Application;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\State\RequestState;
use Throwable;

final class RequestBootedHandler
{
    /**
     * @param  Core<RequestState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(Application $app): void
    {
        try {
            $this->nightwatch->sensor->stage(ExecutionStage::BeforeMiddleware);
        } catch (Throwable $e) {
            $this->nightwatch->report($e);
        }
    }
}
