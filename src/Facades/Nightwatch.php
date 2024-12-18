<?php

namespace Laravel\Nightwatch\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Laravel\Nightwatch\Core
 */
final class Nightwatch extends Facade
{
    /**
     * Get the registered name of the component.
     */
    public static function getFacadeAccessor(): string
    {
        return \Laravel\Nightwatch\Core::class;
    }
}
