<?php

namespace Laravel\Package;

final class IngestSucceededResult
{
    public function __construct(
        public float|int $duration,
    ) {
        //
    }
}
