<?php

namespace Laravel\Nightwatch\Factories;

use Illuminate\Contracts\Foundation\Application;
use Laravel\Nightwatch\Config\Config;
use Laravel\Nightwatch\Contracts\LocalIngest;
use RuntimeException;

class LocalIngestFactory
{
    public function __construct(private Config $config)
    {
        //
    }

    public function __invoke(Application $app): LocalIngest
    {
        $factory = match ($this->config->localIngest) {
            'log' => new LogIngestFactory($this->config),
            'null' => new NullIngestFactory,
            'socket' => new SocketIngestFactory($this->config),
            default => throw new RuntimeException("Unknown local ingest [{$this->config->localIngest}]."),
        };

        return $factory($app);
    }
}
