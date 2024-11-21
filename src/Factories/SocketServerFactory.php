<?php

namespace Laravel\Nightwatch\Factories;

use Illuminate\Contracts\Foundation\Application;
use Laravel\Nightwatch\Config\Config;
use React\EventLoop\LoopInterface;
use React\Socket\LimitingServer;
use React\Socket\ServerInterface;
use React\Socket\TcpServer;

final class SocketServerFactory
{
    public function __construct(private LoopInterface $loop, private Config $config)
    {
        //
    }

    public function __invoke(Application $app): ServerInterface
    {
        $server = new TcpServer($this->config->socketIngest->uri, $this->loop);

        return new LimitingServer($server, $this->config->socketIngest->connectionLimit);
    }
}
