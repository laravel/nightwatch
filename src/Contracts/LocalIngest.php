<?php

namespace Laravel\Nightwatch\Contracts;

use Laravel\Nightwatch\Payload;

/**
 * @internal
 */
interface LocalIngest
{
    public function write(Payload $payload): void;

    public function ping(): void;
}
