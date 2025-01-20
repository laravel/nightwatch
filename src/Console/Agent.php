<?php

namespace Laravel\Nightwatch\Console;

use Illuminate\Console\Command;
use Laravel\Nightwatch\Buffers\StreamBuffer;
use Laravel\Nightwatch\Contracts\RemoteIngest;
use Laravel\Nightwatch\Ingests\Remote\IngestSucceededResult;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Promise\PromiseInterface;
use React\Socket\ConnectionInterface;
use React\Socket\ServerInterface as Server;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;
use WeakMap;

use function date;

/**
 * @internal
 */
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
        private StreamBuffer $buffer,
        private LoopInterface $loop,
        private int|float $timeout,
        private int $delay,
    ) {
        parent::__construct();

        $this->connections = new WeakMap;
    }

    public function handle(Server $server, RemoteIngest $ingest): void
    {
        $server->on('connection', function (ConnectionInterface $connection) use ($ingest) {
            $this->accept($connection);

            $connection->on('data', function (string $chunk) use ($connection) {
                $this->bufferConnectionChunk($connection, $chunk);
            });

            $connection->on('end', function () use ($ingest, $connection) {
                $this->buffer->write($this->flushConnectionBuffer($connection));

                $this->queueOrPerformIngest($ingest, static function (PromiseInterface $response) {
                    $response->then(static function (IngestSucceededResult $result) {
                        echo date('Y-m-d H:i:s')." SUCCESS: Took [{$result->duration}]s.".PHP_EOL;
                    }, static function (Throwable $e) {
                        echo date('Y-m-d H:i:s')." ERROR: {$e->getMessage()}.".PHP_EOL;
                    });
                });
            });

            $connection->on('close', function () use ($connection) {
                $this->evict($connection);
            });

            $connection->on('timeout', function () use ($connection) {
                echo date('Y-m-d H:i:s').' ERROR: Connection timed out.'.PHP_EOL;

                $this->evict($connection);
            });

            $connection->on('error', function (Throwable $e) use ($connection) {
                echo date('Y-m-d H:i:s')." ERROR: Connection error. [{$e->getMessage()}].".PHP_EOL;

                $this->evict($connection);
            });
        });

        $server->on('error', static function (Throwable $e) {
            echo date('Y-m-d H:i:s')."Server error. [{$e->getMessage()}].".PHP_EOL;
        });

        echo date('Y-m-d H:i:s').' Nightwatch agent initiated.'.PHP_EOL;
        $this->loop->run();
    }

    private function accept(ConnectionInterface $connection): void
    {
        $timeoutTimer = $this->loop->addTimer($this->timeout, static function () use ($connection) {
            $connection->emit('timeout');
        });

        $this->connections[$connection] = ['', $timeoutTimer];
    }

    private function bufferConnectionChunk(ConnectionInterface $connection, string $chunk): void
    {
        $this->connections[$connection][0] .= $chunk;
    }

    private function flushConnectionBuffer(ConnectionInterface $connection): string
    {
        $payload = $this->connections[$connection][0];

        $this->evict($connection);

        return $payload;
    }

    private function evict(ConnectionInterface $connection): void
    {
        $timer = $this->connections[$connection][1] ?? null;

        if ($timer !== null) {
            $connection->close();

            $this->loop->cancelTimer($timer);

            unset($this->connections[$connection]);
        }
    }

    /**
     * @param  (callable(PromiseInterface<IngestSucceededResult>): void)  $after
     */
    private function queueOrPerformIngest(RemoteIngest $ingest, callable $after): void
    {
        if ($this->buffer->wantsFlushing()) {
            $records = $this->buffer->flush();

            if ($this->flushBufferAfterDelayTimer !== null) {
                $this->loop->cancelTimer($this->flushBufferAfterDelayTimer);
                $this->flushBufferAfterDelayTimer = null;
            }

            $after($ingest->write($records));
        } elseif ($this->buffer->isNotEmpty()) {
            $this->flushBufferAfterDelayTimer ??= $this->loop->addTimer($this->delay, function () use ($ingest, $after) {
                $records = $this->buffer->flush();

                $this->flushBufferAfterDelayTimer = null;

                $after($ingest->write($records));
            });
        }
    }
}
