<?php

namespace Laravel\Nightwatch;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * @internal
 */
final class NullUserProvider
{
    public function id(): string
    {
        return '';
    }

    public function remember(Authenticatable $user): void
    {
        //
    }
}
