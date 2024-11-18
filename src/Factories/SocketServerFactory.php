<?php

namespace Laravel\Nightwatch\Factories;

use React\EventLoop\Loop;
use React\Socket\LimitingServer;
use React\Socket\TcpServer;

use function is_int;
use function is_string;

class SocketServerFactory
{
    public function __construct(private Loop $loop, private array $config)
    {
        //
    }

    public function __invoke(string $uri, int $connectionLimit)
    {
        $uri = $this->config['agent']['uri'] ?? null;
        if (! is_string($uri)) {
            $uri = '127.0.0.1:2357';
        }

        $connectionLimit = $this->config['agent']['connection_limit'] ?? null;
        if (! is_int($connectionLimit)) {
            $connectionLimit = 20;
        }

        $server = new TcpServer($uri, $this->loop);

        return new LimitingServer($server, $connectionLimit);
    }
}
