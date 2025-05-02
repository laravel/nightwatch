<?php

namespace Laravel\Nightwatch;

use Laravel\Nightwatch\Contracts\LocalIngest;
use RuntimeException;

use function call_user_func;

/**
 * @internal
 */
final class Ingest implements LocalIngest
{
    /**
     * @var (callable(string): string)|null
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
        if ($payload === '[]') {
            return;
        }

        $this->ingest($payload);
    }

    public function ping(): bool
    {
        $response = $this->ingest('PING');

        if ($response === '4:PONG') {
            return true;
        }

        throw new RuntimeException("Unexpected response from agent: [{$response}]");
    }

    private function ingest(string $payload): string
    {
        if ($this->ingest === null) {
            /** @var (callable(string|null $transmitTo, float|null $ingestTimeout, float|null $ingestConnectionTimeout): (callable(string $payload): string)) */
            $factory = require __DIR__.'/../client/entry.php';

            $this->ingest = $factory(
                $this->transmitTo,
                $this->ingestTimeout,
                $this->ingestConnectionTimeout,
            );
        }

        return call_user_func($this->ingest, $payload);
    }
}
