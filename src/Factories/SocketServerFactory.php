<?php

namespace Laravel\Nightwatch\Factories;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Socket\LimitingServer;
use React\Socket\ServerInterface;
use React\Socket\TcpServer;

use function is_int;
use function is_string;

class SocketServerFactory
{
    public function __construct(private LoopInterface $loop) {
        //
    }

    public function __invoke(Application $app): ServerInterface
    {
        /** @var Config */
        $config = $app->make(Config::class);
        [
            'nightwatch.ingest.local.uri' => $uri,
            'nightwatch.ingest.local.connection_limit' => $connectionLimit,
        ] = $config->get([
            'nightwatch.ingest.local.uri',
            'nightwatch.ingest.local.connection_limit',
        ]);

        $server = new TcpServer($uri, $this->loop);

        return new LimitingServer($server, $connectionLimit);
    }
}
