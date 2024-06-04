<?php

namespace Laravel\Package\Console;

use Illuminate\Console\Command;
use Laravel\Package\ConnectionTimedOutException;
use Laravel\Package\Ingest;
use Laravel\Package\IngestFailedException;
use Laravel\Package\IngestSucceededResult;
use Laravel\Package\RecordBuffer;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Promise\PromiseInterface;
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

    /**
     * @var WeakMap<ConnectionInterface, array{ 0: string, 1: TimerInterface }>
     */
    private WeakMap $connections;

    private ?TimerInterface $flushBufferAfterDelayTimer = null;

    public function __construct(
        private RecordBuffer $buffer,
        private Ingest $ingest,
        private Server $server,
        private LoopInterface $loop,
    ) {
        $this->connections = new WeakMap;
    }

    /**
     * TODO
     * - Limit incoming stream length on both ends?
     * - Stop collecting stats when we have too many in-flight requests?
     * - Locally buffer data in files? Worried about build up.
     */
    public function handle(): void
    {
        $this->server->on('connection', function (ConnectionInterface $connection): void {
            $this->line('Connection received.', 'v');

            $this->accept($connection);

            $connection->on('data', function (string $chunk) use ($connection): void {
                $this->line('Data recieved.', 'v');
                $this->line($chunk, 'vvv');

                $this->bufferConnectionChunk($connection, $chunk);
            });

            $connection->on('end', function () use ($connection): void {
                $this->line('Finished recieving data from connection.', 'v');

                $this->buffer->write($this->flushConnectionBuffer($connection));

                $this->performOrQueueIngest(function (string $records): void {
                    $this->line('Ingesting records.', 'v');
                    $this->line($records, 'vvv');
                }, function (PromiseInterface $promise): void {
                    $promise->then(function (IngestSucceededResult $result): void {
                        $this->line("Finished ingesting records after {$result->duration} seconds", 'v');
                    }, function (Throwable $e): void {
                        if ($e instanceof IngestFailedException) {
                            $this->line("Failed ingesting records after {$e->duration} seconds", 'v');

                            /** @var Throwable */
                            $e = $e->getPrevious();
                        }

                        $this->error("Ingesting error [{$e->getMessage()}].");

                        report($e);
                    });
                });
            });

            $connection->on('close', function () use ($connection) {
                $this->line('Connection closed.', 'v');

                $this->evict($connection);
            });

            $connection->on('timeout', function () use ($connection) {
                $this->error('Connection timed out.');

                $connection->close();

                report(new ConnectionTimedOutException('Incoming connection timed out.', [
                    'timeout' => 10,
                    'remote_address' => $connection->getRemoteAddress(),
                ]));
            });

            $connection->on('error', function (Throwable $e) use ($connection) {
                $this->error("Connection error [{$e->getMessage()}].");

                $this->evict($connection);

                report($e);
            });
        });

        $this->server->on('error', function (Throwable $e): void {
            $this->error("Server error [{$e->getMessage()}].");

            report($e);
        });

        $this->line('Nightwatch agent initiated.');
        $this->loop->run();
    }

    private function accept(ConnectionInterface $connection): void
    {
        $timeoutTimer = $this->loop->addPeriodicTimer(10, function () use ($connection): void {
            $connection->emit('timeout');
        });

        $this->connections[$connection] = ['', $timeoutTimer];
    }

    private function bufferConnectionChunk(ConnectionInterface $connection, string $chunk): void
    {
        $this->connections[$connection][0] .= $chunk; // @phpstan-ignore offsetAccess.notFound
    }

    private function flushConnectionBuffer(ConnectionInterface $connection): string
    {
        $data = $this->connections[$connection][0]; // @phpstan-ignore offsetAccess.notFound

        $this->connections[$connection][0] = '';

        return $data;
    }

    private function evict(ConnectionInterface $connection): void
    {
        $this->loop->cancelTimer($this->connections[$connection][1]); // @phpstan-ignore offsetAccess.notFound

        $connection->close();

        unset($this->connections[$connection]);
    }

    /**
     * @param  (callable(string): void)  $before
     * @param  (callable(PromiseInterface<IngestSuccessResult>): void)  $after
     */
    private function performOrQueueIngest(callable $before, callable $after): void
    {
        if ($this->buffer->wantsFlushing()) {
            $records = $this->buffer->flush();

            $before($records);

            if ($this->flushBufferAfterDelayTimer !== null) {
                $this->loop->cancelTimer($this->flushBufferAfterDelayTimer);
                $this->flushBufferAfterDelayTimer = null;
            }

            $after($this->ingest->write($records));
        } else {
            $this->flushBufferAfterDelayTimer ??= $this->loop->addTimer(10, function () use ($before, $after): void {
                $records = $this->buffer->flush();

                $before($records);

                $this->flushBufferAfterDelayTimer = null;

                $after($this->ingest->write($records));
            });
        }
    }
}
