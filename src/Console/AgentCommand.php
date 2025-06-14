<?php

namespace Laravel\Nightwatch\Console;

use Illuminate\Console\Command;
use SensitiveParameter;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * @internal
 */
#[AsCommand(name: 'nightwatch:agent', description: 'Run the Nightwatch agent.')]
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
        {--server=}';

    /**
     * @var string
     */
    protected $description = 'Run the Nightwatch agent.';

    public function __construct(
        #[SensitiveParameter] private ?string $token,
        private ?string $server,
        private ?string $ingestUri,
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $refreshToken = $this->token;

        $listenOn = $this->option('listen-on') ?? $this->ingestUri;

        $authenticationConnectionTimeout = $this->option('auth-connection-timeout');

        $authenticationTimeout = $this->option('auth-timeout');

        $ingestConnectionTimeout = $this->option('ingest-connection-timeout');

        $ingestTimeout = $this->option('ingest-timeout');

        $server = $this->option('server') ?? $this->server;

        require __DIR__.'/../../agent/build/agent.phar';
    }
}
