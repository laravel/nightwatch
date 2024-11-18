<?php

namespace Laravel\Nightwatch\Ingests\Remote;

use React\Promise\Promise;

/**
 * @internal
 */
final class NullIngest
{
    /**
     * @return Promise<IngestSucceededResult>
     */
    public function write(string $payload): Promise
    {
        return new Promise(static fn ($resolve) => $resolve(
            new IngestSucceededResult(0)
        ));
    }
}
