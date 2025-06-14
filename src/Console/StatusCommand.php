<?php

namespace Laravel\Nightwatch\Console;

use Illuminate\Console\Command;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

/**
 * @internal
 */
#[AsCommand(name: 'nightwatch:status', description: 'Get the current status of the Nightwatch agent.')]
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

    /**
     * @param  Core<RequestState|CommandState>  $nightwatch
     */
    public function handle(Core $nightwatch): int
    {
        if (! $nightwatch->enabled()) {
            $this->components->error('Nightwatch is disabled');

            return 1;
        }

        try {
            $nightwatch->ingest->ping();

            $this->components->info('The Nightwatch agent is running and accepting connections');

            return 0;
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return 1;
        }
    }
}
