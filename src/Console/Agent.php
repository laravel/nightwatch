<?php

namespace Laravel\Nightwatch\Console;

use Illuminate\Console\Command;
use Laravel\Nightwatch\AuthToken;
use Laravel\Nightwatch\AuthTokenRepository;
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

    private ?TimerInterface $tokenRenewalTimer = null;

    public function __construct(
        private StreamBuffer $buffer,
        private LoopInterface $loop,
        private int|float $timeout,
        private int $delay,
    ) {
        parent::__construct();

        $this->connections = new WeakMap;
    }

    public function handle(
        Server $server,
        RemoteIngest $ingest,
        AuthTokenRepository $auth,
    ): void {
        $this->authenticate($auth);
        $this->startServer($server, $ingest);

        echo date('Y-m-d H:i:s').' Nightwatch agent initiated.'.PHP_EOL;
        $this->loop->run();
    }

    private function authenticate(AuthTokenRepository $auth): void
    {
        $auth->refresh()->then(function (AuthToken $token) use ($auth) {
            echo date('Y-m-d H:i:s').' Authenticated.'.PHP_EOL;

            // TODO set the new token on the ingest

            $this->scheduleTokenRenewal($auth, $token);
        }, static function (Throwable $e) {
            // TODO tell the ingest to stop sending data to the ingest.
            // TODO retries
            echo date('Y-m-d H:i:s')." ERROR: Failed to authenticate the environment token: [{$e->getMessage()}].".PHP_EOL;
        });
    }

    private function scheduleTokenRenewal(AuthTokenRepository $auth, AuthToken $token): void
    {
        if ($this->tokenRenewalTimer !== null) {
            $this->loop->cancelTimer($this->tokenRenewalTimer);
        }

        // Renew the token 1 minute before it expires.
        $interval = max(60, $token->expiresIn - 60);

        $this->tokenRenewalTimer = $this->loop->addTimer($interval, fn () => $this->authenticate($auth));
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
    }

    private function accept(ConnectionInterface $connection): void
    {
        $timeoutTimer = $this->loop->addPeriodicTimer($this->timeout, static function () use ($connection) {
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

        $this->connections[$connection][0] = '';

        return $payload;
    }

    private function evict(ConnectionInterface $connection): void
    {
        $connection->close();

        $this->loop->cancelTimer($this->connections[$connection][1]);

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
