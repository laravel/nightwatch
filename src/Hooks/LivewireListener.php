<?php

namespace Laravel\Nightwatch\Hooks;

use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\RequestState;
use Livewire\Component;

/**
 * @internal
 */
final class LivewireListener
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
     *
     * Initial request:
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
     *
     * Update request:
     * - component.boot
     * - property.hydrate
     * - component.hydrate
     * - component.hydrate.subsequent
     * - component.booted
     * - component.updating
     * - component.updated
     * - action.returned
     * - component.rendering
     * - component.rendered
     * - view:render
     * - component.dehydrate
     * - component.dehydrate.subsequent
     * - property.dehydrate
     */

    public function componentHydrateSubsequent(Component $component): void
    {
        $this->nightwatch->captureRequestRouteAction($component::class);
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
     * - render
     * - view:compile
     * - dehydrate
     * - checksum.generate
     * - destroy
     * - response
     */

    public function hydrate(Component $component): void
    {
        $this->nightwatch->captureRequestRouteAction($component::class);
    }
}
