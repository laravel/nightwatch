<?php

namespace Laravel\Nightwatch\Factories;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Laravel\Nightwatch\Ingests\SocketIngest;
use React\Socket\TcpConnector;
use React\Socket\TimeoutConnector;

class SocketIngestFactory
{
    public function __invoke(Application $app): SocketIngest
    {
        /** @var Repository */
        $config = $app->make('config');

        $connector = new TimeoutConnector(
            new TcpConnector,
            $config->get('nightwatch.collector.connection_timeout'),
        );

        $uri = $config->get('nightwatch.agent.address').':'.$config->get('nightwatch.agent.port');

        return new SocketIngest($connector, $uri);
    }
}
