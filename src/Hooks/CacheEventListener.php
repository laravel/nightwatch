<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Cache\Events\CacheEvent;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Throwable;

final class CacheEventListener
{
    /**
     * @param  Core<RequestState|CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(CacheEvent $event): void
    {
        try {
            $this->nightwatch->sensor->cacheEvent($event);
        } catch (Throwable $e) {
            $this->nightwatch->report($e);
        }
    }
}
