<?php

namespace Laravel\Nightwatch;

use Illuminate\Auth\AuthManager;
use Laravel\Nightwatch\Types\TinyText;

final class UserProvider
{
    public function __construct(private AuthManager $auth)
    {
        //
    }

    public function id(): string
    {
        return TinyText::limit((string) $this->auth->id());
    }
}
