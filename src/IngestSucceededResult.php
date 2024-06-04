<?php

namespace Laravel\Nightwatch;

final class IngestSucceededResult
{
    public function __construct(
        public float|int $duration,
    ) {
        //
    }
}
