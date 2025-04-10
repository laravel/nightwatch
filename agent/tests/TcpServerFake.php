<?php

namespace Tests;

use Evenement\EventEmitter;
use React\Socket\ServerInterface;
use RuntimeException;

use function json_encode;

class TcpServerFake extends EventEmitter implements ServerInterface
{
    /**
     * @param  list<array<string, mixed>>  $records
     */
    public function pendingConnection(array $records): PendingConnection
    {
        return new PendingConnection($this, json_encode($records, flags: JSON_THROW_ON_ERROR));
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
