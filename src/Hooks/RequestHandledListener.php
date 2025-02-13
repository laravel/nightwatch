<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\State\RequestState;
use Throwable;

/**
 * @internal
 */
final class RequestHandledListener
{
    /**
     * @param  Core<RequestState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(RequestHandled $event): void
    {
        try {
            $this->nightwatch->sensor->stage(ExecutionStage::Sending);
        } catch (Throwable $e) {
            $this->nightwatch->report($e);
        }
    }
}
