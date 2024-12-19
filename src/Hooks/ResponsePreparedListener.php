<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Routing\Events\ResponsePrepared;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\State\RequestState;
use Throwable;

final class ResponsePreparedListener
{
    /**
     * @param  Core<RequestState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(ResponsePrepared $event): void
    {
        try {
            if ($this->nightwatch->state->stage === ExecutionStage::Render) {
                $this->nightwatch->sensor->stage(ExecutionStage::AfterMiddleware);
            }
        } catch (Throwable $e) {
            $this->nightwatch->report($e);
        }
    }
}
