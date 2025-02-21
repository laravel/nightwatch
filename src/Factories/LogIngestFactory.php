<?php

namespace Laravel\Nightwatch\Factories;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Log\LogManager;
use Laravel\Nightwatch\Ingests\LogIngest;

/**
 * @internal
 */
final class LogIngestFactory
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

    public function __invoke(Application $app): LogIngest
    {
        /** @var LogManager */
        $log = $app->make(LogManager::class);

        return new LogIngest($log->channel($this->config['ingests']['log']['channel'] ?? null));
    }
}
