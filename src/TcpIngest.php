<?php

namespace Laravel\Nightwatch;

use Laravel\Nightwatch\Contracts\Ingest;
use React\Socket\ConnectorInterface;

use function React\Async\await;

final class TcpIngest implements Ingest
{
    public function __construct(
        private ConnectorInterface $connector,
        private string $uri,
    ) {
        //
    }

    /**
     * TODO: retry / fallback logic
     * TODO protocol?
     * TODO: should we put a timeout on this side?
     * TODO: error handling?
     * $timeoutTimer = Loop::addTimer($timeout, function () use ($connection) {
     *     $this->error('Sending data timed out.');
     *     $connection->close();
     * });
     */
    public function write(string $payload): void
    {
        $connection = await($this->connector->connect($this->uri));

        $connection->end($payload);
    }
}
