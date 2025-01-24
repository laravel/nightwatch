<?php

namespace Laravel\Nightwatch\Factories;

use Illuminate\Contracts\Foundation\Application;
use Laravel\Nightwatch\IngestDetailsRepository;
use React\Http\Browser;
use React\Socket\Connector;

final class IngestDetailsRepositoryFactory
{
    /**
     * @param  array{
     *      enabled?: bool,
     *      env_id?: string,
     *      token?: string,
     *      auth_url?: string,
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
        private array $config,
    ) {
        //
    }

    public function __invoke(Application $app): IngestDetailsRepository
    {
        $token = $this->config['token'] ?? '';

        $connector = new Connector(['timeout' => 5]);

        $browser = (new Browser($connector))
            ->withTimeout(10)
            ->withHeader('authorization', "Bearer {$token}")
            ->withHeader('user-agent', 'NightwatchAgent/1')
            ->withHeader('content-type', 'application/json')
            ->withBase($this->config['auth_url'] ?? '');

        return new IngestDetailsRepository($browser);
    }
}
