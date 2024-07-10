<?php

namespace Laravel\Nightwatch\Ingests;

use Laravel\Nightwatch\Contracts\Ingest;
use React\Socket\ConnectorInterface;
use Throwable;

use function React\Async\await;

final class SocketIngest implements Ingest
{
    public function __construct(
        private ConnectorInterface $connector,
        private string $uri,
    ) {
        //
    }

    /**
     * TODO retry / fallback logic
     * TODO protocol?
     * TODO should we put a timeout on this side?
     * $timeoutTimer = Loop::addTimer($timeout, function () use ($connection) {
     *     $this->error('Sending data timed out.');
     *     $connection->close();
     * });
     * TODO error handling?
     */
    public function write(string $payload): void
    {
        if ($payload === '') {
            return;
        }

        // try {
            $connection = await($this->connector->connect($this->uri));

            $connection->end($payload);
        // } catch (Throwable $e) {
        //     // TODO what to do with this failure?
        // }
    }
}
