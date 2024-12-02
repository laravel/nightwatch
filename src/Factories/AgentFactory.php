<?php

namespace Laravel\Nightwatch\Factories;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Env;
use Laravel\Nightwatch\Buffers\StreamBuffer;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Console\Agent;
use Laravel\Nightwatch\Contracts\RemoteIngest;
use React\EventLoop\StreamSelectLoop;
use React\Http\Browser;
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
    public function __construct(private Clock $clock, private array $config)
    {
        //
    }

    public function __invoke(Application $app): Agent
    {
        $debug = (bool) Env::get('NIGHTWATCH_DEBUG');
        $loop = new StreamSelectLoop;

        // Creating an instance of the `TcpServer` will automatically start the
        // server.  To ensure do not start the server when the command is
        // constructed, but only when called, we make sure to resolve the
        // server in the handle method instead instead when constructing.
        $app->when([Agent::class, 'handle'])
            ->needs(ServerInterface::class)
            ->give((new SocketServerFactory($loop, $this->config))(...));

        // Creating an instance of the `Browser` may fail if the configuration
        // values are incorrect, for example, an empty string for the ingest
        // URI.  To ensure we do not throw an exception when the command is
        // resolved, which happens simply by running the `php artisan list`
        // command, we we make sure to resolve the server in the handle method
        // instead of when constructing.
        $app->when([Agent::class, 'handle'])
            ->needs(RemoteIngest::class)
            ->give((new HttpIngestFactory($loop, $this->clock, $this->config, $debug))(...));

        return new Agent(new StreamBuffer, $loop, $this->config['ingests']['socket']['timeout'] ?? 0.5, $debug ? 1 : 10);
    }
}
