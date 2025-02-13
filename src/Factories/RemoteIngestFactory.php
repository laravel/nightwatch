<?php

namespace Laravel\Nightwatch\Factories;

use Illuminate\Contracts\Foundation\Application;
use Laravel\Nightwatch\Contracts\RemoteIngest;
use Laravel\Nightwatch\IngestDetailsRepository;
use RuntimeException;

/**
 * @internal
 */
final class RemoteIngestFactory
{
    /**
     * @param  array{
     *      enabled?: bool,
     *      token?: string,
     *      auth_url?: string,
     *      deployment?: string,
     *      server?: string,
     *      local_ingest?: string,
     *      remote_ingest?: string,
     *      buffer_threshold?: int,
     *      error_log_channel?: string,
     *      ingests: array{
     *          socket?: array{ uri?: string, connection_timeout?: float, timeout?: float },
     *          http?: array{ connection_limit?: int, connection_timeout?: float, timeout?: float },
     *          log?: array{ channel?: string },
     *      }
     * }  $config
     */
    public function __construct(
        private array $config,
        private IngestDetailsRepository $ingestDetails,
        private bool $debug,
    ) {
        //
    }

    public function __invoke(Application $app): RemoteIngest
    {
        if ($app->bound(RemoteIngest::class)) {
            return $app->make(RemoteIngest::class);
        }

        $name = $this->config['remote_ingest'] ?? 'http';

        $factory = match ($name) {
            'null' => new NullRemoteIngestFactory,
            'http' => new HttpIngestFactory($this->config, $this->ingestDetails, $this->debug),
            default => throw new RuntimeException("Unknown remote ingest [{$name}]."),
        };

        return $factory($app);
    }
}
