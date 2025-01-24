<?php

namespace Laravel\Nightwatch;

final class IngestDetails
{
    public function __construct(
        public string $token,
        public int $expiresIn,
        public string $ingestUrl,
    ) {
        //
    }
}
