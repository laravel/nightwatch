<?php

namespace Laravel\Nightwatch\Factories;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Laravel\Nightwatch\Ingests\Local\SocketIngest;
use React\Socket\TcpConnector;
use React\Socket\TimeoutConnector;

class SocketIngestFactory
{
    public function __invoke(Application $app): SocketIngest
    {
        /** @var Config */
        $config = $app->make(Config::class);
        [
            'nightwatch.ingest.local.uri' => $uri,
            'nightwatch.ingest.local.connection_timeout' => $connectionTimeout,
            'nightwatch.ingest.local.timeout' => $timeout,
        ] = $config->get([
            'nightwatch.ingest.local.uri',
            'nightwatch.ingest.local.connection_timeout',
            'nightwatch.ingest.local.timeout',
        ]);

        // TODO confirm this timeout is working.
        $connector = new TcpConnector(context: ['timeout' => $timeout]);

        $connector = new TimeoutConnector($connector, $connectionTimeout);

        return new SocketIngest($connector, $uri);
    }
}
