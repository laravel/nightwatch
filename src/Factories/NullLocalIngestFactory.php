<?php

namespace Laravel\Nightwatch\Factories;

use Laravel\Nightwatch\Ingests\NullIngest;

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
