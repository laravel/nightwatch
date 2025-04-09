<?php

namespace Tests;

use Evenement\EventEmitter;
use React\Socket\ServerInterface;
use RuntimeException;

class TcpServerFake extends EventEmitter implements ServerInterface
{
    public function pendingConnection(string $payload): PendingConnection
    {
        return new PendingConnection($this, $payload);
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
