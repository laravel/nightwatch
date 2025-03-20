<?php

namespace Laravel\Nightwatch\Records;

use Laravel\Nightwatch\Types\Str;

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
        $this->id = Str::tinyText($this->id);
        $this->name = Str::tinyText($this->name);
        $this->username = Str::tinyText($this->username);
    }
}
