<?php

namespace Laravel\Nightwatch\Hooks;

use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Laravel\Octane\Events\RequestReceived;
use Laravel\Octane\Events\TaskReceived;
use Laravel\Octane\Events\TickReceived;
use Throwable;

class OctaneListener
{
    /**
     * @param  Core<RequestState|CommandState>  $nightwatch
     */
    public function __construct(private Core $nightwatch)
    {
        //
    }

    public function __invoke(RequestReceived|TaskReceived|TickReceived $event): void // @phpstan-ignore class.notFound, class.notFound, class.notFound
    {
        try {
            $this->nightwatch->prepareForNextOctaneOperation();
        } catch (Throwable $e) {
            $this->nightwatch->report($e);
        }
    }
}
