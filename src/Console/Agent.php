<?php

namespace Laravel\Nightwatch\Console;

use Illuminate\Console\Command;
use Laravel\Nightwatch\Buffers\StreamBuffer;
use Laravel\Nightwatch\Contracts\RemoteIngest;
use Laravel\Nightwatch\IngestDetailsRepository;
use Laravel\Nightwatch\Ingests\Remote\IngestSucceededResult;
use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;
use React\Promise\PromiseInterface;
use React\Socket\ConnectionInterface;
use React\Socket\ServerInterface as Server;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;
use WeakMap;

use function date;
use function max;

/**
 * @internal
 */
#[AsCommand(name: 'nightwatch:agent')]
final class Agent extends Command
{
    /**
     * @var string
     */
    protected $signature = 'nightwatch:agent {--base-url=}';

    /**
     * @var string
     */
    protected $description = 'Start the Nightwatch agent.';

    /**
     * @var WeakMap<ConnectionInterface, string>
     */
    private WeakMap $connections;

    private ?TimerInterface $flushBufferAfterDelayTimer = null;

    private ?TimerInterface $tokenRenewalTimer = null;

    public function __construct(
        private StreamBuffer $buffer,
        private int $delay,
    ) {
        parent::__construct();

        $this->connections = new WeakMap;
    }

    public function handle(
        Server $server,
        RemoteIngest $ingest,
        IngestDetailsRepository $ingestDetails,
    ): void {
        $this->refresh($ingestDetails);
        $this->startServer($server, $ingest);

        echo date('Y-m-d H:i:s').' Nightwatch agent initiated.'.PHP_EOL;
        Loop::run();
    }

    private function refresh(IngestDetailsRepository $ingestDetails): void
    {
        $ingestDetails->refresh()->then(function () use ($ingestDetails) {
            echo date('Y-m-d H:i:s').' Authenticated.'.PHP_EOL;

            $this->scheduleRefresh($ingestDetails);
        }, static function (Throwable $e) {
            // TODO retries
            echo date('Y-m-d H:i:s')." ERROR: Failed to authenticate the environment token: [{$e->getMessage()}].".PHP_EOL;
        });
    }

    private function scheduleRefresh(IngestDetailsRepository $ingestDetails): void
    {
        if ($this->tokenRenewalTimer !== null) {
            Loop::cancelTimer($this->tokenRenewalTimer);
        }

        // Renew the token 1 minute before it expires.
        $interval = max(60, $ingestDetails->get()?->expiresIn - 60);

        $this->tokenRenewalTimer = Loop::addTimer($interval, fn () => $this->refresh($ingestDetails));
    }

    private function startServer(Server $server, RemoteIngest $ingest): void
    {
        $server->on('connection', function (ConnectionInterface $connection) use ($ingest) {
            $this->accept($connection);

            $connection->on('data', function (string $chunk) use ($connection) {
                $this->bufferConnectionChunk($connection, $chunk);
            });

            $connection->on('end', function () use ($ingest, $connection) {
                $this->buffer->write($this->flushConnectionBuffer($connection));

                Loop::futureTick(function () use ($ingest) {
                    $this->queueOrPerformIngest($ingest, static function (PromiseInterface $response) {
                        $response->then(static function (IngestSucceededResult $result) {
                            echo date('Y-m-d H:i:s')." SUCCESS: Took [{$result->duration}]s.".PHP_EOL;
                        }, static function (Throwable $e) {
                            echo date('Y-m-d H:i:s')." ERROR: {$e->getMessage()}.".PHP_EOL;
                        });
                    });
                });
            });

            $connection->on('close', function () use ($connection) {
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
    }

    private function accept(ConnectionInterface $connection): void
    {
        $this->connections[$connection] = '';
    }

    private function bufferConnectionChunk(ConnectionInterface $connection, string $chunk): void
    {
        $this->connections[$connection] .= $chunk;
    }

    private function flushConnectionBuffer(ConnectionInterface $connection): string
    {
        $payload = $this->connections[$connection];

        $this->evict($connection);

        return $payload;
    }

    private function evict(ConnectionInterface $connection): void
    {
        $connection->close();

        unset($this->connections[$connection]);
    }

    /**
     * @param  (callable(PromiseInterface<IngestSucceededResult>): void)  $after
     */
    private function queueOrPerformIngest(RemoteIngest $ingest, callable $after): void
    {
        if ($this->buffer->wantsFlushing()) {
            $records = $this->buffer->flush();

            if ($this->flushBufferAfterDelayTimer !== null) {
                Loop::cancelTimer($this->flushBufferAfterDelayTimer);
                $this->flushBufferAfterDelayTimer = null;
            }

            $after($ingest->write($records));
        } elseif ($this->buffer->isNotEmpty()) {
            $this->flushBufferAfterDelayTimer ??= Loop::addTimer($this->delay, function () use ($ingest, $after) {
                $records = $this->buffer->flush();

                $this->flushBufferAfterDelayTimer = null;

                $after($ingest->write($records));
            });
        }
    }
}
