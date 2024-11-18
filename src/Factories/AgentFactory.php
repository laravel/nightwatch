<?php

namespace Laravel\Nightwatch\Factories;

use Illuminate\Contracts\Foundation\Application;
use Laravel\Nightwatch\Console\Agent;
use React\Socket\ServerInterface;

use function is_array;
use function is_int;
use function is_string;

class AgentFactory
{
    public function __invoke(Application $app)
    {
        /** @var Config */
        $repository = $app->make('config');
        $config = $config->get('nightwatch');
        if (! is_array($config)) {
            $config = [];
        }

        $uri = $this->config['agent']['uri'] ?? null;
        if (! is_string($uri)) {
            $uri = '127.0.0.1:2357';
        }

        $connectionLimit = $this->config['agent']['connection_limit'] ?? null;
        if (! is_int($connectionLimit)) {
            $connectionLimit = 20;
        }

        /** @var Clock */
        $clock = $app->make(Clock::class);

        $loop = new StreamSelectLoop;

        // Creating an instance of the `TcpServer` will automatically start
        // the server.  To ensure do not start the server when the command
        // is constructed, but only when called, we make sure to resolve
        // the server in the handle method instead.
        $app->when([Agent::class, 'handle'])
            ->needs(ServerInterface::class)
            ->give(new SocketServerFactory($loop, $uri, $connectionLimit));

        $buffer = new PayloadBuffer($config->get('nightwatch.agent.buffer_threshold'));

        $connector = new Connector([
            'timeout' => $config->get('nightwatch.http.connection_timeout'),
        ], $loop);

        $client = new Client((new Browser($connector, $loop))
            ->withTimeout($config->get('nightwatch.agent.timeout'))
            ->withHeader('User-Agent', 'NightwatchAgent/1')
            ->withHeader('Content-Type', 'application/octet-stream')
            ->withHeader('Content-Encoding', 'gzip')
            ->withHeader('Nightwatch-App-Id', $config->get('nightwatch.app_id'))
            ->withBase('https://khq5ni773stuucqrxebn3a5zbi0ypexu.lambda-url.us-east-1.on.aws/'));

        $ingest = new HttpIngest($client, $clock, $config->get('nightwatch.http.concurrent_request_limit'));
        // $ingest = new NullIngest;

        return new Agent($buffer, $ingest, $loop, $config->get('nightwatch.collector.timeout'));
    }
}
