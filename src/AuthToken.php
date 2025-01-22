<?php

namespace Laravel\Nightwatch;

final class AuthToken
{
    public function __construct(
        public string $token,
        public int $expiresIn,
    ) {
        //
    }
}
