<?php

namespace Laravel\Package;

class IngestSucceededResult
{
    public function __construct(
        public float|int $duration,
    ) {
        //
    }
}
