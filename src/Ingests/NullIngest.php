<?php

namespace Laravel\Nightwatch\Ingests;

use Laravel\Nightwatch\Contracts\Ingest;
use Laravel\Nightwatch\IngestSucceededResult;
use React\Promise\Promise;

use function React\Promise\resolve;

final class NullIngest
{
    public function write(string $payload): Promise
    {
        return new Promise(function ($resolve) {
            $resolve(new IngestSucceededResult(0));
        });
    }
}
