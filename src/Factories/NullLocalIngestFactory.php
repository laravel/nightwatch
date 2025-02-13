<?php

namespace Laravel\Nightwatch\Factories;

use Laravel\Nightwatch\Ingests\Local\NullIngest;

/**
 * @internal
 */
final class NullLocalIngestFactory
{
    public function __invoke(): NullIngest
    {
        return new NullIngest;
    }
}
