<?php

namespace Laravel\Nightwatch\Contracts;

/**
 * @internal
 */
interface Ingest
{
    public function write(string $payload): void;
}
