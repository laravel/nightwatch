<?php

namespace Laravel\Nightwatch;

use Illuminate\Auth\AuthManager;
use Laravel\Nightwatch\Types\Str;

/**
 * @internal
 */
final class UserProvider
{
    public function __construct(private AuthManager $auth)
    {
        //
    }

    public function id(): string
    {
        // TODO: see pulse user ID resolution
        return Str::tinyText((string) $this->auth->id());
    }
}
