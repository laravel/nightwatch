<?php

namespace Laravel\NightwatchClient;

use React\Socket\ConnectorInterface;

use function React\Async\await;
use function strlen;

class Ingest
{
    public function __construct(
        private ConnectorInterface $connector,
        private string $transmitTo,
    ) {
        //
    }

    public function __invoke(string $payload): void
    {
        await($this->connector->connect($this->transmitTo))->end(strlen($payload).':'.$payload);
    }
}
