<?php

namespace Laravel\Nightwatch\Hooks;

use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\RequestState;

class LivewireListener
{
    /**
     * @param  Core<RequestState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch
    ) {
        //
    }

    // Livewire 2
    public function componentBoot(...$params): void
    {
        $this->nightwatch->executionState->routeAction = $params[0]::class ?? null;
    }

    // Livewire 3
    public function preMount(...$params): void
    {
        $this->nightwatch->executionState->routeAction = $params[0] ?? null;
    }
}
