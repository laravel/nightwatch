<?php

namespace Laravel\NightwatchAgent;

use Closure;
use React\Socket\ConnectionInterface;
use React\Socket\ServerInterface;
use RuntimeException;
use Throwable;

use function call_user_func;

class Server
{
    /**
     * @param  (Closure(): ServerInterface)  $serverResolver
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
        //
    }

    public function start(): void
    {
        $server = call_user_func($this->serverResolver);

        $server->on('connection', function (ConnectionInterface $connection): void {
            $payload = new Payload;

            $connection->on('data', function (string $chunk) use ($connection, $payload): void {
                $payload->append($chunk);

                if ($payload->complete) {
                    match ($payload->value) {
                        'PING' => $connection->end('4:PONG'),
                        default => call_user_func($this->onPayloadReceived, $payload->value),
                    };
                }
            });

            $connection->on('close', function () use ($payload) {
                if (! $payload->complete) {
                    call_user_func($this->onConnectionError, new RuntimeException("Incomplete payload received. Length: [{$payload->length}] Value: [{$payload->value}]"));
                }
            });

            $connection->on('error', function (Throwable $e): void {
                call_user_func($this->onConnectionError, $e);
            });
        });

        $server->on('error', function (Throwable $e): void {
            call_user_func($this->onServerError, $e);
        });

        call_user_func($this->onServerStarted);
    }
}
