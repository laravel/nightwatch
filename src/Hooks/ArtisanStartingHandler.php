<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Console\Application as Artisan;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\State\CommandState;
use Throwable;

final class ArtisanStartingHandler
{
    public function __construct(private CommandState $commandState)
    {
        //
    }

    public function __invoke(Artisan $artisan): void
    {
        try {
            $this->commandState->artisan = $artisan;
        } catch (Throwable $e) { // @phpstan-ignore catch.neverThrown
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }
}
