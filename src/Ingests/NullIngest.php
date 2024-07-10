<?php

namespace Laravel\Nightwatch\Ingests;

use Laravel\Nightwatch\IngestSucceededResult;
use React\Promise\Promise;

final class NullIngest
{
    /**
     * @return Promise<IngestSucceededResult>
     */
    public function write(string $payload): Promise
    {
        echo "Payload size: ".strlen($payload)." bytes";

        return new Promise(fn ($resolve) => $resolve(
            new IngestSucceededResult(0)
        ));
    }
}
