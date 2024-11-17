<?php

namespace Laravel\Nightwatch\Factories;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Laravel\Nightwatch\Ingests\Local\SocketIngest;
use React\Socket\TcpConnector;
use React\Socket\TimeoutConnector;

use function is_array;
use function is_float;
use function is_numeric;
use function is_string;

class SocketIngestFactory
{
    public function __invoke(Application $app): SocketIngest
    {
        /** @var Repository */
        $repository = $app->make('config');
        $config = $repository->get('nightwatch');
        if (! is_array($config)) {
            $config = [];
        }

        $timeout = $config['collector']['connection_timeout'] ?? null;
        if (! is_float($timeout)) {
            if (is_numeric($timeout)) {
                $timeout = (float) $timeout;
            } else {
                $timeout = 0.2;
            }
        }

        $uri = $config['agent']['uri'] ?? null;
        if (! is_string($uri)) {
            $uri = '127.0.0.1:2357';
        }

        $connector = new TimeoutConnector(new TcpConnector, $timeout);

        return new SocketIngest($connector, $uri);
    }
}
