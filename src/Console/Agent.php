<?php

namespace Laravel\Nightwatch\Console;

use Illuminate\Console\Command;
use Laravel\Nightwatch\Exceptions\ConnectionTimedOutException;
use Laravel\Nightwatch\Ingest;
use Laravel\Nightwatch\IngestFailedException;
use Laravel\Nightwatch\IngestSucceededResult;
use Laravel\Nightwatch\RecordBuffer;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Promise\PromiseInterface;
use React\Socket\ConnectionInterface;
use React\Socket\ServerInterface as Server;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;
use WeakMap;

#[AsCommand(name: 'nightwatch:agent')]
final class Agent extends Command
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
        private int|float $timeout,
    ) {
        parent::__construct();

        $this->connections = new WeakMap;
    }

    /**
     * TODO
     * - Limit incoming stream length on both ends?
     * - Locally buffer data in files? Worried about build up. Opt-in failover
     * mechanism, esp if we gzip the contents first. Might not be too bad.
     */
    public function handle(): int
    {
        $this->server->on('connection', function (ConnectionInterface $connection): void {
            $this->line('Connection accepted.', verbosity: 'v');

            $this->accept($connection);

            $connection->on('data', function (string $chunk) use ($connection): void {
                $this->line('Data recieved.', verbosity: 'v');
                $this->line($chunk, verbosity: 'vvv');

                $this->bufferConnectionChunk($connection, $chunk);
            });

            $connection->on('end', function () use ($connection): void {
                $this->line('Connection ended.', verbosity: 'v');

                $this->buffer->write($this->flushConnectionBuffer($connection));

                $this->queueOrPerformIngest(
                    before: function (string $records): void {
                        $this->line('Ingesting started.', verbosity: 'v');
                        $this->line($records, verbosity: 'vvv');
                    },
                    after: function (PromiseInterface $response): void {
                        $response->then(function (IngestSucceededResult $result) {
                            $this->line("Records successfully ingested after {$result->duration} ms", verbosity: 'v');
                        }, function (Throwable $e) {
                            if ($e instanceof IngestFailedException) {
                                $this->error("Records failed ingesting after {$e->duration} ms", verbosity: 'v');
                                $this->line("Reason: {$e->getMessage()}", verbosity: 'v');

                                /** @var Throwable */
                                $e = $e->getPrevious();
                            }

                            $this->error("Ingesting error [{$e->getMessage()}].");

                            report($e);
                        });
                    });
            });

            $connection->on('close', function () use ($connection) {
                $this->line('Connection closed.', verbosity: 'v');

                $this->evict($connection);
            });

            $connection->on('timeout', function () use ($connection) {
                $this->error('Connection timed out.');

                $connection->close();

                report(new ConnectionTimedOutException('Incoming connection timed out.', [
                    'timeout' => $this->timeout,
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

        $this->line('🌗 Nightwatch agent initiated.');
        $this->loop->run();
    }

    private function accept(ConnectionInterface $connection): void
    {
        $timeoutTimer = $this->loop->addPeriodicTimer($this->timeout, function () use ($connection): void {
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
        $connection->close();

        $this->loop->cancelTimer($this->connections[$connection][1]); // @phpstan-ignore offsetAccess.notFound

        unset($this->connections[$connection]);
    }

    /**
     * @param  (callable(string): void)  $before
     * @param  (callable(PromiseInterface<IngestSucceededResult>): void)  $after
     */
    private function queueOrPerformIngest(callable $before, callable $after): void
    {
        if ($this->buffer->wantsFlushing()) {
            $records = $this->buffer->flush();

            $before($records);

            if ($this->flushBufferAfterDelayTimer !== null) {
                $this->loop->cancelTimer($this->flushBufferAfterDelayTimer);
                $this->flushBufferAfterDelayTimer = null;
            }

            $after($this->ingest->write($records));
        } elseif ($this->buffer->isNotEmpty()) {
            // TODO update flush timer duration from 1 to 10
            $this->flushBufferAfterDelayTimer ??= $this->loop->addTimer(1, function () use ($before, $after): void {
                $records = $this->buffer->flush();

                $before($records);

                $this->flushBufferAfterDelayTimer = null;

                $after($this->ingest->write($records));
            });
        }
    }
}
