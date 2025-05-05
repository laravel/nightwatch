<?php

namespace Tests;

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

        $connection->emit('data', [$this->payload]);

        $connection->emit('close');

        $this->server->connections[] = $connection;
    }
}
