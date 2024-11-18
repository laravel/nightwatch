<?php

namespace Laravel\Nightwatch\Factories;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Log\LogManager;
use Laravel\Nightwatch\Contracts\LocalIngest;
use Laravel\Nightwatch\Ingests\Local\LogIngest;
use Laravel\Nightwatch\Ingests\Local\NullIngest;

class LocalIngestFactory
{
    public function __invoke(Application $app): LocalIngest
    {
        /** @var Config */
        $config = $app->make(Config::class);
        /** @var string */
        $driver = $config->get('nightwatch.ingest.local.driver') ?? 'socket';

        $factory = match ($driver) {
            'log' => static fn (Application $app) => new LogIngest($app->make(LogManager::class)),
            'null' => static fn (Application $app) => new NullIngest,
            default => new SocketIngestFactory,
        };

        return $factory($app);
    }
}
