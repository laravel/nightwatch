<?php

namespace Laravel\Nightwatch\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\Application;
use Laravel\Nightwatch\GracefulCliOutputExceptionHandler;
use SensitiveParameter;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

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
        {--server=}
        {--silent : Do not output any message}';

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

    public function handle(Application $app): void
    {
        try {
            $handler = $app->instance(
                ExceptionHandler::class,
                new GracefulCliOutputExceptionHandler($app->make(ExceptionHandler::class))
            );
        } catch (Throwable) {
            //
        }

        $refreshToken = $this->token;

        $listenOn = $this->option('listen-on') ?? $this->ingestUri;

        $authenticationConnectionTimeout = $this->option('auth-connection-timeout');

        $authenticationTimeout = $this->option('auth-timeout');

        $ingestConnectionTimeout = $this->option('ingest-connection-timeout');

        $ingestTimeout = $this->option('ingest-timeout');

        $server = $this->option('server') ?? $this->server;

        $silent = $this->option('silent') ?: null;

        $quiet = $this->option('quiet') ?: null;

        $verbose = $this->option('verbose') ?: null;

        require __DIR__.'/../../agent/build/agent.phar';

        if (isset($handler)) {
            $handler->shuttingDown();
        }
    }
}
