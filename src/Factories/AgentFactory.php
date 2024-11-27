<?php

namespace Laravel\Nightwatch\Factories;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Env;
use Laravel\Nightwatch\Buffers\StreamBuffer;
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
    /**
     * @param  array{
     *      enabled?: bool,
     *      env_id?: string,
     *      env_secret?: string,
     *      deployment?: string,
     *      server?: string,
     *      local_ingest?: string,
     *      remote_ingest?: string,
     *      buffer_threshold?: int,
     *      error_log_channel?: string,
     *      ingests: array{
     *          socket?: array{ uri?: string, connection_limit?: int, connection_timeout?: float, timeout?: float },
     *          http?: array{ uri?: string, connection_limit?: int, connection_timeout?: float, timeout?: float },
     *          log?: array{ channel?: string },
     *      }
     * }  $config
     */
    public function __construct(private array $config)
    {
        //
    }

    public function __invoke(Application $app): Agent
    {
        $loop = new StreamSelectLoop;
        $connector = new Connector(['timeout' => $this->config['ingests']['http']['connection_timeout'] ?? 1.0], $loop);

        // Creating an instance of the `TcpServer` will automatically start
        // the server.  To ensure do not start the server when the command
        // is constructed, but only when called, we make sure to resolve
        // the server in the handle method instead.
        $app->when([Agent::class, 'handle'])
            ->needs(ServerInterface::class)
            ->give((new SocketServerFactory($loop, $this->config))(...));

        $client = new Client((new Browser($connector, $loop))
            ->withTimeout($this->config['ingests']['http']['timeout'] ?? 3.0)
            ->withHeader('User-Agent', 'NightwatchAgent/1')
            ->withHeader('Content-Type', 'application/octet-stream')
            ->withHeader('Content-Encoding', 'gzip')
            // TODO this should be "env" id
            ->withHeader('Nightwatch-App-Id', $this->config['env_id'] ?? '')
            ->withBase($this->config['ingests']['http']['uri'] ?? ''), (bool) Env::get('NIGHTWATCH_DEBUG') ? '/?debug=1' : '/');

        /** @var Clock */
        $clock = $app->make(Clock::class);
        $buffer = new StreamBuffer;
        $ingest = new HttpIngest($client, $clock, $this->config['ingests']['http']['connection_limit'] ?? 2);

        return new Agent($buffer, $ingest, $loop, $this->config['ingests']['socket']['timeout'] ?? 0.5);
    }
}
