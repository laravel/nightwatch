<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Console\Events\ArtisanStarting;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\CommandState;
use Throwable;

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
        try {
            $this->nightwatch->state->artisan = $event->artisan;
        } catch (Throwable $e) { // @phpstan-ignore catch.neverThrown
            $this->nightwatch->report($e);
        }
    }
}
