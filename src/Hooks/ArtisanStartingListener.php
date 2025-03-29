<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Console\Events\ArtisanStarting;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\CommandState;

/**
 * @internal
 */
final class ArtisanStartingListener
{
    /**
     * @param  Core<CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(ArtisanStarting $event): void
    {
        $this->nightwatch->state->artisan = $event->artisan;
    }
}
