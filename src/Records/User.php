<?php

namespace Laravel\Nightwatch\Records;

/**
 * @internal
 */
final class User
{
    public int $v = 1;

    public string $t = 'user';

    public function __construct(
        public float $timestamp,
        public string $id,
        public string $name,
        public string $username,
    ) {
        //
    }
}
