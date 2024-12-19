<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Console\Application as Artisan;
use Laravel\Nightwatch\SensorManager;
use Laravel\Nightwatch\State\CommandState;
use Throwable;

final class ArtisanStartingHandler
{
    public function __construct(
        private SensorManager $sensor, // @phpstan-ignore property.onlyWritten
        private CommandState $commandState,
    ) {
        //
    }

    public function __invoke(Artisan $artisan): void
    {
        try {
            $this->commandState->artisan = $artisan;
        } catch (Throwable $e) { // @phpstan-ignore catch.neverThrown
            $this->sensor->exception($e);
        }
    }
}
