<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Console\Application as Artisan;
use Laravel\Nightwatch\State\CommandState;

final class ArtisanStartingHandler
{
    public function __construct(private CommandState $commandState)
    {
        //
    }

    public function __invoke(Artisan $artisan): void
    {
        $this->commandState->artisan = $artisan;
    }
}
