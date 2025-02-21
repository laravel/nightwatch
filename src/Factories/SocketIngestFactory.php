<?php

namespace Laravel\Nightwatch\Factories;

use Illuminate\Contracts\Foundation\Application;
use Laravel\Nightwatch\Ingests\SocketIngest;
use React\Socket\TcpConnector;
use React\Socket\TimeoutConnector;

/**
 * @internal
 */
final class SocketIngestFactory
{
    /**
     * @param  array{
     *      enabled?: bool,
     *      token?: string,
     *      deployment?: string,
     *      server?: string,
     *      local_ingest?: string,
     *      remote_ingest?: string,
     *      buffer_threshold?: int,
     *      error_log_channel?: string,
     *      ingests: array{
     *          socket?: array{ uri?: string, connection_timeout?: float, timeout?: float },
     *          http?: array{ connection_timeout?: float, timeout?: float },
     *          log?: array{ channel?: string },
     *      }
     * }  $config
     */
    public function __construct(
        private array $config,
    ) {
        //
    }

    public function __invoke(Application $app): SocketIngest
    {
        $connector = new TcpConnector(context: ['timeout' => $this->config['ingests']['socket']['timeout'] ?? 0.5]);

        $connector = new TimeoutConnector($connector, $this->config['ingests']['socket']['connection_timeout'] ?? 0.5);

        return new SocketIngest($connector, $this->config['ingests']['socket']['uri'] ?? '');
    }
}
