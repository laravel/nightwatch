<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Http\Client\Factory;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Throwable;

/**
 * @internal
 */
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
             */
            $factory->globalMiddleware($this->nightwatch->guzzleMiddleware());
        } catch (Throwable $e) {
            $this->nightwatch->report($e, handled: true);
        }
    }
}
