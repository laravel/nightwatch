<?php

namespace Laravel\Nightwatch\Factories;

use Illuminate\Contracts\Foundation\Application;
use Laravel\Nightwatch\Buffers\PayloadBuffer;
use Laravel\Nightwatch\Client;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Config\Config;
use Laravel\Nightwatch\Console\Agent;
use Laravel\Nightwatch\Ingests\Remote\HttpIngest;
use React\EventLoop\StreamSelectLoop;
use React\Http\Browser;
use React\Socket\Connector;
use React\Socket\ServerInterface;

class AgentFactory
{
    public function __construct(private Config $config)
    {
        //
    }

    public function __invoke(Application $app): Agent
    {
        $loop = new StreamSelectLoop;
        $connector = new Connector(['timeout' => $this->config->httpIngest->connectionTimeout], $loop);

        // Creating an instance of the `TcpServer` will automatically start
        // the server.  To ensure do not start the server when the command
        // is constructed, but only when called, we make sure to resolve
        // the server in the handle method instead.
        $app->when([Agent::class, 'handle'])
            ->needs(ServerInterface::class)
            ->give((new SocketServerFactory($loop, $this->config))(...));

        $client = new Client((new Browser($connector, $loop))
            ->withTimeout($this->config->httpIngest->timeout)
            ->withHeader('User-Agent', 'NightwatchAgent/1')
            ->withHeader('Content-Type', 'application/octet-stream')
            ->withHeader('Content-Encoding', 'gzip')
            ->withHeader('Nightwatch-Env-Id', $this->config->envId)
            ->withBase($this->config->httpIngest->uri));

        /** @var Clock */
        $clock = $app->make(Clock::class);
        $buffer = new PayloadBuffer;
        $ingest = new HttpIngest($client, $clock, $this->config->httpIngest->connectionLimit);

        return new Agent($buffer, $ingest, $loop, $this->config->socketIngest->timeout);
    }
}
