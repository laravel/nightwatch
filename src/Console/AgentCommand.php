<?php

namespace Laravel\Nightwatch\Console;

use Illuminate\Console\Command;
use SensitiveParameter;
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

    public function __construct(
        #[SensitiveParameter] private ?string $token,
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $refreshToken = $this->token;

        $baseUrl = $this->option('base-url');

        $listenOn = $this->option('listen-on');

        $authenticationConnectionTimeout = $this->option('auth-connection-timeout');

        $authenticationTimeout = $this->option('auth-timeout');

        $ingestConnectionTimeout = $this->option('ingest-connection-timeout');

        $ingestTimeout = $this->option('ingest-timeout');

        require __DIR__.'/../../agent/build/agent.phar';
    }
}
