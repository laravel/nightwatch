<?php

namespace Laravel\Nightwatch\Factories;

use Illuminate\Contracts\Foundation\Application;
use Laravel\Nightwatch\Contracts\RemoteIngest;
use React\EventLoop\LoopInterface;
use RuntimeException;

final class RemoteIngestFactory
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
        private array $config,
        private bool $debug,
    ) {
        //
    }

    public function __invoke(Application $app): RemoteIngest
    {
        $name = $this->config['remote_ingest'] ?? 'http';

        $factory = match ($name) {
            'null' => new NullRemoteIngestFactory,
            'http' => new HttpIngestFactory($this->loop, $this->config, $this->debug),
            default => throw new RuntimeException("Unknown remote ingest [{$name}]."),
        };

        return $factory($app);
    }
}
