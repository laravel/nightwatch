<?php

namespace Laravel\Nightwatch\Contracts;

/**
 * @internal
 */
interface LocalIngest
{
    public function write(string $payload): void;
}
