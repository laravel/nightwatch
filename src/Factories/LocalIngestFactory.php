<?php

namespace Laravel\Nightwatch\Factories;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Laravel\Nightwatch\Contracts\LocalIngest;

final class LocalIngestFactory
{
    public function __invoke(Application $app): LocalIngest
    {
        /** @var Config */
        $config = $app->make(Config::class);
        /** @var string */
        $driver = $config->get('nightwatch.ingest.local.driver') ?? 'socket';

        $factory = match ($driver) {
            'log' => new LogIngestFactory,
            'null' => new NullIngestFactory,
            default => new SocketIngestFactory,
        };

        return $factory($app);
    }
}
