<?php

namespace Tests;

use Evenement\EventEmitter;
use React\Socket\ServerInterface;
use RuntimeException;

class TcpServerFake extends EventEmitter implements ServerInterface
{
    /**
     * @param  string|list<array<string, mixed>>  $records
     */
    public function pendingConnection(array|string $records): PendingConnection
    {
        return new PendingConnection($this, $records);
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
