<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Console\Application as Artisan;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\CommandState;
use Throwable;

/**
 * @internal
 */
final class ArtisanStartingHandler
{
    /**
     * @param  Core<CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(Artisan $artisan): void
    {
        try {
            $this->nightwatch->state->artisan = $artisan;
        } catch (Throwable $e) { // @phpstan-ignore catch.neverThrown
            $this->nightwatch->report($e);
        }
    }
}
