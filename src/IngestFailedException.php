<?php

namespace Laravel\Nightwatch;

use RuntimeException;
use Throwable;

final class IngestFailedException extends RuntimeException
{
    public function __construct(
        public int $duration,
        Throwable $previous,
    ) {
        parent::__construct('Ingesting failed.', previous: $previous);
    }
}
