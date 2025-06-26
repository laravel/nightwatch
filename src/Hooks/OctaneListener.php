<?php

namespace Laravel\Nightwatch\Hooks;

use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Laravel\Octane\Events\RequestReceived;
use Throwable;

class OctaneListener
{
    private bool $firstRequest = true;

    /**
     * @param  Core<RequestState|CommandState>  $nightwatch
     */
    public function __construct(private Core $nightwatch)
    {
        //
    }

    public function __invoke(RequestReceived $event): void // @phpstan-ignore class.notFound
    {
        if ($this->firstRequest) {
            $this->firstRequest = false;

            return;
        }

        try {
            $this->nightwatch->prepareForNextRequest();
        } catch (Throwable $e) {
            $this->nightwatch->report($e);
        }
    }
}
