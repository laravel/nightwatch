<?php

namespace Laravel\Nightwatch\Factories;

use Illuminate\Contracts\Foundation\Application;
use Laravel\Nightwatch\IngestDetailsRepository;
use React\Http\Browser;
use React\Socket\Connector;

/**
 * @internal
 */
final class IngestDetailsRepositoryFactory
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
        private string $base,
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
            ->withBase("{$this->base}/api/agent-auth");

        return new IngestDetailsRepository($browser);
    }
}
