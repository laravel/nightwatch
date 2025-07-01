<?php

namespace Laravel\Nightwatch\Hooks;

use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\RequestState;
use Livewire\Component;

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

    public function componentBoot(Component $component): void
    {
        $this->nightwatch->executionState->routeAction = $component::class;
    }

    // Livewire 3

    public function preMount(string $component): void
    {
        $this->nightwatch->executionState->routeAction = $component;
    }
}
