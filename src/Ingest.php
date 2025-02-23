<?php

namespace Laravel\Nightwatch;

use Laravel\Nightwatch\Contracts\LocalIngest;

/**
 * @internal
 */
final class Ingest implements LocalIngest
{
    public function __construct(
        private ?string $transmitTo,
        private ?float $ingestTimeout,
        private ?float $ingestConnectionTimeout,
    ) {
        //
    }

    public function write(string $payload): void
    {
        $transmitTo = $this->transmitTo;

        $ingestTimeout = $this->ingestTimeout;

        $ingestConnectionTimeout = $this->ingestConnectionTimeout;

        require __DIR__.'/../client/build/client.phar';
    }
}
