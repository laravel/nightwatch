<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Routing\Events\PreparingResponse;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\State\RequestState;
use Throwable;

final class PreparingResponseListener
{
    /**
     * @param  Core<RequestState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(PreparingResponse $event): void
    {
        try {
            if ($this->nightwatch->state->stage === ExecutionStage::Action) {
                $this->nightwatch->sensor->stage(ExecutionStage::Render);
            }
        } catch (Throwable $e) {
            $this->nightwatch->report($e);
        }
    }
}
