<?php

namespace Laravel\NightwatchClient;

use React\Socket\ConnectorInterface;

use function React\Async\await;

class Ingest
{
    public function __construct(
        private ConnectorInterface $connector,
        private string $transmitTo,
    ) {
        //
    }

    public function write(string $payload): void
    {
        if ($payload !== '') {
            await($this->connector->connect($this->transmitTo))->end($payload);
        }
    }
}
