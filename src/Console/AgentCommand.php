<?php

namespace Laravel\Nightwatch\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as Config;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * @internal
 */
#[AsCommand(name: 'nightwatch:agent')]
final class AgentCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'nightwatch:agent
        {--listen-on=}
        {--auth-connection-timeout=}
        {--auth-timeout=}
        {--ingest-connection-timeout=}
        {--ingest-timeout=}
        {--base-url=}';

    /**
     * @var string
     */
    protected $description = 'Run the Nightwatch agent.';

    public function handle(Config $config): void
    {
        /**
         * @var array{
         *     enabled?: bool,
         *     token?: string,
         *     deployment?: string,
         *     server?: string,
         *     local_ingest?: string,
         *     remote_ingest?: string,
         *     buffer_threshold?: int,
         *     error_log_channel?: string,
         *     ingests: array{
         *         socket?: array{ uri?: string, connection_timeout?: float, timeout?: float },
         *         http?: array{ connection_timeout?: float, timeout?: float },
         *         log?: array{ channel?: string },
         *     }
         *  } $c
         */
        $c = $config->all()['nightwatch'] ?? [];

        $refreshToken = $c['token'] ?? null;

        $baseUrl = $this->option('base-url');

        $listenOn = $this->option('listen-on');

        $authenticationConnectionTimeout = $this->option('auth-connection-timeout');

        $authenticationTimeout = $this->option('auth-timeout');

        $ingestConnectionTimeout = $this->option('ingest-connection-timeout');

        $ingestTimeout = $this->option('ingest-timeout');

        require __DIR__.'/../../agent/build/agent.phar';
    }
}
