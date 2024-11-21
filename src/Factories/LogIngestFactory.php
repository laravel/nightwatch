<?php

namespace Laravel\Nightwatch\Factories;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Log\LogManager;
use Laravel\Nightwatch\Config\Config;
use Laravel\Nightwatch\Ingests\Local\LogIngest;

final class LogIngestFactory
{
    public function __construct(private Config $config)
    {
        //
    }

    public function __invoke(Application $app): LogIngest
    {
        /** @var LogManager */
        $log = $app->make(LogManager::class);

        return new LogIngest($log->channel($this->config->logIngest->channel));
    }
}
