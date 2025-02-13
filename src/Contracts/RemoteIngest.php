<?php

namespace Laravel\Nightwatch\Contracts;

use Laravel\Nightwatch\Ingests\Remote\IngestSucceededResult;
use React\Promise\PromiseInterface;

/**
 * @internal
 */
interface RemoteIngest
{
    /**
     * @return PromiseInterface<IngestSucceededResult>
     */
    public function write(string $payload): PromiseInterface;
}
