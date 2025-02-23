<?php

namespace Laravel\NightwatchClient;

use React\Socket\TcpConnector;
use React\Socket\TimeoutConnector;

class IngestFactory
{
    public function __invoke(
        string $transmitTo,
        float $ingestTimeout,
        float $ingestConnectionTimeout,
    ): Ingest {
        $connector = new TcpConnector(context: ['timeout' => $ingestTimeout]);

        $connector = new TimeoutConnector($connector, $ingestConnectionTimeout);

        return new Ingest($connector, $transmitTo);
    }
}
