<?php

namespace Laravel\Nightwatch;

use Illuminate\Auth\AuthManager;
use Laravel\Nightwatch\Types\Str;
use Laravel\Nightwatch\Types\TinyText;

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
        return Str::tinyText((string) $this->auth->id());
    }
}
