<?php

namespace Tests;

use Evenement\EventEmitter;
use React\Socket\ServerInterface;
use RuntimeException;

use function is_string;
use function json_encode;

class TcpServerFake extends EventEmitter implements ServerInterface
{
    /**
     * @var list<Connection>
     */
    public array $connections = [];

    /**
     * @param  list<array<string, mixed>>  $payload
     */
    public function pendingConnection(array|string $payload): PendingConnection
    {
        return new PendingConnection($this, is_string($payload)
            ? $payload
            : json_encode($payload, flags: JSON_THROW_ON_ERROR));
    }

    public function getAddress()
    {
        throw new RuntimeException(__FUNCTION__);
    }

    public function pause()
    {
        throw new RuntimeException(__FUNCTION__);
    }

    public function resume()
    {
        throw new RuntimeException(__FUNCTION__);
    }

    public function close()
    {
        throw new RuntimeException(__FUNCTION__);
    }
}
