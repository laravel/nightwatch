<?php

namespace Laravel\Nightwatch\Ingests;

use Laravel\Nightwatch\IngestSucceededResult;
use React\Promise\Promise;

final class NullIngest
{
    public function write(string $payload): Promise
    {
        return new Promise(function ($resolve) {
            $resolve(new IngestSucceededResult(0));
        });
    }
}
