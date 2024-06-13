<?php

namespace Laravel\Nightwatch\Ingests;

use Laravel\Nightwatch\Contracts\Ingest;

final class NullIngest implements Ingest
{
    public function write(string $payload): void
    {
        //
    }
}
