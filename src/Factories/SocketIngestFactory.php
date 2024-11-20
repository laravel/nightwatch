<?php

namespace Laravel\Nightwatch\Factories;

use Illuminate\Contracts\Foundation\Application;
use Laravel\Nightwatch\Config\Config;
use Laravel\Nightwatch\Ingests\Local\SocketIngest;
use React\Socket\TcpConnector;
use React\Socket\TimeoutConnector;

class SocketIngestFactory
{
    public function __construct(private Config $config)
    {
        //
    }

    public function __invoke(Application $app): SocketIngest
    {
        // TODO confirm this timeout is working.
        $connector = new TcpConnector(context: ['timeout' => $this->config->socketIngest->timeout]);

        $connector = new TimeoutConnector($connector, $this->config->socketIngest->connectionTimeout);

        return new SocketIngest($connector, $this->config->socketIngest->uri);
    }
}
