<?php

namespace Laravel\Nightwatch\Ingests\Remote;

/**
 * @internal
 */
final class IngestSucceededResult
{
    public function __construct(public float $duration)
    {
        //
    }
}
