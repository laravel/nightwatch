<?php

namespace Laravel\Nightwatch\Factories;

use Laravel\Nightwatch\Ingests\Remote\NullIngest;

final class NullRemoteIngestFactory
{
    public function __invoke(): NullIngest
    {
        return new NullIngest;
    }
}
