<?php

namespace Laravel\NightwatchAgent;

use Closure;
use React\Socket\Connection;
use React\Socket\TcpServer;
use Throwable;
use WeakMap;

use function call_user_func;

class Server
{
    /**
     * @var WeakMap<Connection, string>
     */
    private WeakMap $connections;

    /**
     * @param  (Closure(): TcpServer)  $serverResolver
     * @param  (Closure(): mixed)  $onServerStarted
     * @param  (Closure(Throwable $e): mixed)  $onServerError
     * @param  (Closure(Throwable $e): mixed)  $onConnectionError
     * @param  (Closure(string $payload): mixed)  $onPayloadReceived
     */
    public function __construct(
        private Closure $serverResolver,
        private Closure $onServerStarted,
        private Closure $onServerError,
        private Closure $onConnectionError,
        private Closure $onPayloadReceived,
    ) {
        $this->connections = new WeakMap;
    }

    public function start(): void
    {
        $server = call_user_func($this->serverResolver);

        $server->on('connection', function (Connection $connection): void {
            $this->accept($connection);

            $connection->on('data', function (string $chunk) use ($connection): void {
                $this->bufferConnectionChunk($connection, $chunk);
            });

            $connection->on('end', function () use ($connection): void {
                call_user_func($this->onPayloadReceived, $this->flushConnectionBuffer($connection));
            });

            $connection->on('close', function () use ($connection): void {
                $this->evict($connection);
            });

            $connection->on('error', function (Throwable $e) use ($connection): void {
                $this->evict($connection);

                call_user_func($this->onConnectionError, $e);
            });
        });

        $server->on('error', function (Throwable $e): void {
            call_user_func($this->onServerError, $e);
        });

        call_user_func($this->onServerStarted);
    }

    private function accept(Connection $connection): void
    {
        $this->connections[$connection] = '';
    }

    private function bufferConnectionChunk(Connection $connection, string $chunk): void
    {
        $this->connections[$connection] .= $chunk;
    }

    private function flushConnectionBuffer(Connection $connection): string
    {
        $payload = $this->connections[$connection];

        $this->evict($connection);

        return $payload;
    }

    private function evict(Connection $connection): void
    {
        $connection->close();

        unset($this->connections[$connection]);
    }
}
