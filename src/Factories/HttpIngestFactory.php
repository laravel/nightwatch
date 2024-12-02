<?php

namespace Laravel\Nightwatch\Factories;

use Illuminate\Contracts\Foundation\Application;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Ingests\Remote\HttpClient;
use Laravel\Nightwatch\Ingests\Remote\HttpIngest;
use React\EventLoop\LoopInterface;
use React\Http\Browser;
use React\Socket\Connector;

final class HttpIngestFactory
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
    public function __construct(
        private LoopInterface $loop,
        private Clock $clock,
        private array $config,
        private bool $debug,
    ) {
        //
    }

    public function __invoke(Application $app): HttpIngest
    {
        $connector = new Connector(['timeout' => $this->config['ingests']['http']['connection_timeout'] ?? 1.0], $this->loop);

        $client = new HttpClient((new Browser($connector, $this->loop))
            ->withTimeout($this->config['ingests']['http']['timeout'] ?? 3.0)
            ->withHeader('user-agent', 'NightwatchAgent/1')
            ->withHeader('content-type', 'application/octet-stream')
            ->withHeader('content-encoding', 'gzip')
            // TODO this should be "env" id
            ->withHeader('nightwatch-app-id', $this->config['env_id'] ?? '')
            ->withBase($this->config['ingests']['http']['uri'] ?? ''), $this->debug ? '?debug=1' : '');

        return new HttpIngest($client, $this->clock, $this->config['ingests']['http']['connection_limit'] ?? 2);
    }
}
