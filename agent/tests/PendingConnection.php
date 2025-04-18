<?php

namespace Tests;

use function strlen;

class PendingConnection
{
    public function __construct(
        public TcpServerFake $server,
        public string $payload,
    ) {
        //
    }

    public function __invoke(): void
    {
        $connection = new Connection;

        $this->server->emit('connection', [$connection]);

        $connection->emit('data', [strlen($this->payload).':'.$this->payload]);

        $connection->emit('end');
    }
}
