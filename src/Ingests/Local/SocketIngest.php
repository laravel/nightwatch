<?php

namespace Laravel\Nightwatch\Ingests\Local;

use Laravel\Nightwatch\Contracts\LocalIngest;
use React\Socket\ConnectorInterface;
use Throwable;

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
        if ($payload === '') {
            return;
        }

        $connection = await($this->connector->connect($this->uri));

        $connection->end($payload);
    }
}
