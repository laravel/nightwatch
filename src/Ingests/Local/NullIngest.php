<?php

namespace Laravel\Nightwatch\Ingests\Local;

use Laravel\Nightwatch\Contracts\LocalIngest;

class NullIngest implements LocalIngest
{
    public function write(string $payload): void
    {
        //
    }
}
