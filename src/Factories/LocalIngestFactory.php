<?php

namespace Laravel\Nightwatch\Factories;

use Illuminate\Contracts\Foundation\Application;
use Laravel\Nightwatch\Contracts\LocalIngest;
use RuntimeException;

final class LocalIngestFactory
{
    /**
     * @param  array{
     *      enabled?: bool,
     *      env_id?: string,
     *      env_secret?: string,
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
    public function __construct(private array $config)
    {
        //
    }

    public function __invoke(Application $app): LocalIngest
    {
        $name = $this->config['local_ingest'] ?? 'socket';

        $factory = match ($name) {
            'null' => new NullLocalIngestFactory,
            'log' => new LogIngestFactory($this->config),
            'socket' => new SocketIngestFactory($this->config),
            default => throw new RuntimeException("Unknown local ingest [{$name}]."),
        };

        return $factory($app);
    }
}
