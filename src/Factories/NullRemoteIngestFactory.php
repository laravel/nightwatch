<?php

namespace Laravel\Nightwatch\Factories;

use Laravel\Nightwatch\Ingests\Remote\NullIngest;

class NullRemoteIngestFactory
{
    public function __invoke()
    {
        return new NullIngest;
    }
}
