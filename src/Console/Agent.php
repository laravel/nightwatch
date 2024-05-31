<?php

namespace Laravel\Package\Console;

use Illuminate\Console\Command;
use Laravel\Package\Buffer;
use Laravel\Package\ConnectionManager;
use Laravel\Package\Ingest;
use React\EventLoop\Loop;
use React\Socket\ConnectionInterface;
use React\Socket\ServerInterface as Server;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;
use WeakMap;

#[AsCommand(name: 'nightwatch:agent')]
class Agent extends Command
{
    /**
     * @var string
     */
    protected $signature = 'nightwatch:agent';

    /**
     * @var string
     */
    protected $description = 'Start the Nightwatch agent.';

    public function __construct(
        private Server $server,
        private ConnectionManager $connections,
    ) {
        //
    }


    // TODO: HTTP retry logic in here or the command?
    public function handle(): void
    {
        $this->server->on('connection', function (ConnectionInterface $connection): void {
            $this->connections->handle($connection);
        });

        $this->server->on('error', function (Throwable $e): void {
            $this->error('Server error ['.$e::class.':'.$e->getMessage().'].');
        });

        $this->connections->onTimeout(function (ConnectionInterface $connection): void {
            $this->error('Connection timed out.');
        });

        $this->connections->onError(function (ConnectionInterface $connection, Throwable $e): void {
            $this->error('Connection error ['.$e::class.':'.$e->getMessage().'].');
        });

        $this->connections->onEnd(function (ConnectionInterface $connection, string $data): void {
            $this->buffer->write($data);

            if ($this->buffer->wantsFlushing()) {
                Loop::cancelTimer($this->flushAfterDelayTimer);
                $this->flushAfterDelayTimer = null;

                $this->ingest->flush($this->buffer);

                $this->scheduleFlushAfterDelay();
            }
        });

        $this->scheduleFlushAfterDelay();

        $this->line("Nightwatch agent initiated.");
    }
}

