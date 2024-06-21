<?php

namespace Laravel\Nightwatch\Console;

use Illuminate\Console\Command;
use Laravel\Nightwatch\Buffers\PayloadBuffer;
use Laravel\Nightwatch\Exceptions\IngestFailedException;
use Laravel\Nightwatch\Ingests\HttpIngest;
use Laravel\Nightwatch\Ingests\NullIngest;
use Laravel\Nightwatch\IngestSucceededResult;
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

    private ?TimerInterface $flushBufferAfterDelayTimer;

    public function __construct(
        private PayloadBuffer $buffer,
        private HttpIngest|NullIngest $ingest,
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
    public function handle(Server $server): void
    {
        $server->on('connection', function (ConnectionInterface $connection) {
            $this->accept($connection);

            $connection->on('data', function (string $chunk) use ($connection) {
                $this->bufferConnectionChunk($connection, $chunk);
            });

            $connection->on('end', function () use ($connection) {
                $this->buffer->write($this->flushConnectionBuffer($connection));

                $this->queueOrPerformIngest(function (PromiseInterface $response) {
                    $response->then(function (IngestSucceededResult $result) {
                        echo "Ingest successful. Took {$result->duration} ms.".PHP_EOL;
                    }, function (IngestFailedException $e) {
                        echo "Failed failed. Took {$e->duration} ms. [{$e->getPrevious()->getMessage()}].".PHP_EOL;
                    });
                });
            });

            $connection->on('close', function () use ($connection) {
                $this->evict($connection);
            });

            $connection->on('timeout', function () use ($connection) {
                $this->error('Connection timed out.');

                $connection->close();
            });

            $connection->on('error', function (Throwable $e) use ($connection) {
                $this->error("Connection error. [{$e->getMessage()}].");

                $this->evict($connection);
            });
        });

        $server->on('error', function (Throwable $e) {
            $this->error("Server error. [{$e->getMessage()}].");
        });

        echo 'Nightwatch agent initiated.'.PHP_EOL;
        $this->loop->run();
    }

    private function accept(ConnectionInterface $connection): void
    {
        $timeoutTimer = $this->loop->addPeriodicTimer($this->timeout, function () use ($connection) {
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
        $payload = $this->connections[$connection][0]; // @phpstan-ignore offsetAccess.notFound

        $this->connections[$connection][0] = '';

        return $payload;
    }

    private function evict(ConnectionInterface $connection): void
    {
        $connection->close();

        $this->loop->cancelTimer($this->connections[$connection][1]); // @phpstan-ignore offsetAccess.notFound

        unset($this->connections[$connection]);
    }

    /**
     * @param  (callable(PromiseInterface<IngestSucceededResult>): void)  $after
     */
    private function queueOrPerformIngest(callable $after): void
    {
        if ($this->buffer->wantsFlushing()) {
            $records = $this->buffer->flush();

            if ($this->flushBufferAfterDelayTimer !== null) {
                $this->loop->cancelTimer($this->flushBufferAfterDelayTimer);
                $this->flushBufferAfterDelayTimer = null;
            }

            $after($this->ingest->write($records));
        } elseif ($this->buffer->isNotEmpty()) {
            $this->flushBufferAfterDelayTimer ??= $this->loop->addTimer(10, function () use ($after) {
                $records = $this->buffer->flush();

                $this->flushBufferAfterDelayTimer = null;

                $after($this->ingest->write($records));
            });
        }
    }
}
