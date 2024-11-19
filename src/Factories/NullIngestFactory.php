<?php

namespace Laravel\Nightwatch\Factories;

use Laravel\Nightwatch\Ingests\Local\NullIngest;

class NullIngestFactory
{
    public function __invoke(): NullIngest
    {
        return new NullIngest;
    }
}
