<?php

namespace Laravel\Nightwatch;

use Laravel\Nightwatch\Contracts\LocalIngest;

use function call_user_func;

/**
 * @internal
 */
final class Ingest implements LocalIngest
{
    /**
     * @var (callable(string): void)|null
     */
    private $ingest = null;

    public function __construct(
        private ?string $transmitTo,
        private ?float $ingestTimeout,
        private ?float $ingestConnectionTimeout,
    ) {
        //
    }

    public function write(string $payload): void
    {
        if ($this->ingest === null) {
            /** @var (callable(string|null $transmitTo, float|null $ingestTimeout, float|null $ingestConnectionTimeout): (callable(string $payload): void)) */
            $factory = require __DIR__.'/../client/entry.php';

            $this->ingest = $factory(
                $this->transmitTo,
                $this->ingestTimeout,
                $this->ingestConnectionTimeout,
            );
        }

        call_user_func($this->ingest, $payload);
    }
}
