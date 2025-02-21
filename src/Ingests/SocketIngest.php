<?php

namespace Laravel\Nightwatch\Ingests;

use Laravel\Nightwatch\Contracts\LocalIngest;
use React\Socket\ConnectorInterface;

use function React\Async\await;

/**
 * @internal
 */
final class SocketIngest implements LocalIngest
{
    public function __construct(
        private ConnectorInterface $connector,
        private string $uri,
    ) {
        //
    }

    public function write(string $payload): void
    {
        if ($payload !== '') {
            await($this->connector->connect($this->uri))->end($payload);
        }
    }
}
