<?php

namespace Tests;

use function is_string;
use function json_encode;
use function strlen;

class PendingConnection
{
    /**
     * @param  string|array<mixed>  $payload
     */
    public function __construct(
        private TcpServerFake $server,
        private string|array $payload,
    ) {
        //
    }

    public function __invoke(): void
    {
        $connection = new Connection;

        $this->server->emit('connection', [$connection]);

        $connection->emit('data', [$this->payload()]);

        $connection->emit('end');

        $connection->emit('close');
    }

    private function payload(): string
    {
        if (is_string($this->payload)) {
            return $this->payload;
        }

        $payload = json_encode($this->payload, flags: JSON_THROW_ON_ERROR);

        return strlen($payload).':'.$payload;
    }
}
