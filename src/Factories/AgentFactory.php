<?php

namespace Laravel\Nightwatch\Factories;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Laravel\Nightwatch\Buffers\PayloadBuffer;
use Laravel\Nightwatch\Client;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Console\Agent;
use Laravel\Nightwatch\Ingests\Remote\HttpIngest;
use React\EventLoop\StreamSelectLoop;
use React\Http\Browser;
use React\Socket\Connector;
use React\Socket\ServerInterface;

final class AgentFactory
{
    public function __invoke(Application $app): Agent
    {
        /** @var Config */
        $config = $app->make(Config::class);
        /**
         * @var string $appId
         * @var string $uri
         * @var int $connectionLimit
         * @var int $connectionTimeout
         * @var int $timeout
         * @var int $bufferThreshold
         * @var int $localTimeout
         */
        [
            'nightwatch.app_id' => $appId,
            'nightwatch.ingest.remote.uri' => $uri,
            'nightwatch.ingest.remote.connection_limit' => $connectionLimit,
            'nightwatch.ingest.remote.connection_timeout' => $connectionTimeout,
            'nightwatch.ingest.remote.timeout' => $timeout,
            'nightwatch.ingest.remote.buffer_threshold' => $bufferThreshold,
            'nightwatch.ingest.local.timeout' => $localTimeout,
        ] = $config->get([
            'nightwatch.app_id',
            'nightwatch.ingest.remote.uri',
            'nightwatch.ingest.remote.connection_limit',
            'nightwatch.ingest.remote.connection_timeout',
            'nightwatch.ingest.remote.timeout',
            'nightwatch.ingest.remote.buffer_threshold',
            'nightwatch.ingest.local.timeout',
        ]);

        $loop = new StreamSelectLoop;
        $connector = new Connector(['timeout' => $connectionTimeout], $loop);

        // Creating an instance of the `TcpServer` will automatically start
        // the server.  To ensure do not start the server when the command
        // is constructed, but only when called, we make sure to resolve
        // the server in the handle method instead.
        $app->when([Agent::class, 'handle'])
            ->needs(ServerInterface::class)
            ->give((new SocketServerFactory($loop))(...));

        $client = new Client((new Browser($connector, $loop))
            ->withTimeout($timeout)
            ->withHeader('User-Agent', 'NightwatchAgent/1')
            ->withHeader('Content-Type', 'application/octet-stream')
            ->withHeader('Content-Encoding', 'gzip')
            ->withHeader('Nightwatch-App-Id', $appId)
            ->withBase($uri));

        /** @var Clock */
        $clock = $app->make(Clock::class);
        $buffer = new PayloadBuffer($bufferThreshold);
        $ingest = new HttpIngest($client, $clock, $connectionLimit);

        return new Agent($buffer, $ingest, $loop, $localTimeout);
    }
}
