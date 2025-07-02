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

    /* Livewire 2 Events
     * - component.boot
     * - component.hydrate
     * - component.hydrate.initial
     * - component.mount
     * - component.booted
     * - component.rendering
     * - component.rendered
     * - view:render
     * - component.dehydrate
     * - component.dehydrate.initial
     * - property.dehydrate
     * - mounted
     */

    public function componentBoot(Component $component): void
    {
        $this->nightwatch->executionState->routeAction = $component::class;
    }

    /* Livewire 3 Events
     *
     * Initial request:
     * - pre-mount
     * - mount
     * - render
     * - view:compile
     * - dehydrate
     * - checksum:generate
     * - destroy
     *
     * Update request:
     * - request
     * - checksum.verify
     * - checksum.generate
     * - snapshot-verified
     * - hydrate
     * - update
     * - call
     * - call
     * - call
     * - render
     * - view:compile
     * - dehydrate
     * - checksum.generate
     * - destroy
     * - response
     */

    public function hydrate(Component $component): void
    {
        $this->nightwatch->executionState->routeAction = $component::class;
    }
}
