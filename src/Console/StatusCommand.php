<?php

namespace Laravel\Nightwatch\Console;

use Illuminate\Console\Command;
use Laravel\Nightwatch\Ingest;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

/**
 * @internal
 */
#[AsCommand(name: 'nightwatch:status')]
final class StatusCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'nightwatch:status';

    /**
     * @var string
     */
    protected $description = 'Get the current status of the Nightwatch agent.';

    public function handle(Ingest $ingest): int
    {
        try {
            $response = $ingest->ping();

            if ($response !== '4:PONG') {
                throw new RuntimeException("Unexpected response from the agent [{$response}]");
            }

            $this->components->success('The agent is running and accepting connections.');

            return 0;
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return 1;
        }
    }
}
