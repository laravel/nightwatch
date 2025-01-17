<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Http\Client\Factory;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Throwable;

final class HttpClientFactoryResolvedHandler
{
    /**
     * @param  Core<RequestState|CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(Factory $factory): void
    {
        try {
            /**
             * @see \Laravel\Nightwatch\Records\OutgoingRequest
             *
             * TODO check this isn't a memory leak in octane
             */
            $factory->globalMiddleware(new GuzzleMiddleware($this->nightwatch));
        } catch (Throwable $e) {
            $this->nightwatch->report($e);
        }
    }
}
