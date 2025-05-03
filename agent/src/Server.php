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
     * @param  (Closure(): mixed)  $onInvaidSignature
     */
    public function __construct(
        private Closure $serverResolver,
        private string $signature,
        private Closure $onServerStarted,
        private Closure $onServerError,
        private Closure $onConnectionError,
        private Closure $onPayloadReceived,
        private Closure $onInvaidSignature,
    ) {
        //
    }

    public function start(): void
    {
        /** @var ServerInterface $server */
        $server = call_user_func($this->serverResolver);

        $server->on('connection', function (ConnectionInterface $connection) use ($server): void {
            $payload = new Payload;

            $connection->on('data', function (string $chunk) use ($connection, $payload): void {
                $payload->append($chunk);

                if (! $payload->complete) {
                    return;
                }

                $connection->end('2:OK');
            });

            $connection->on('close', function () use ($server, $payload) {
                if (! $payload->complete) {
                    call_user_func($this->onConnectionError, new RuntimeException("Incomplete payload received. Length: [{$payload->length}] Value: [{$payload->value}]"));

                    return;
                }

                if ($payload->signature !== $this->signature) {
                    $server->close();

                    call_user_func($this->onInvaidSignature);

                    return;
                }

                if ($payload->value === 'PING') {
                    return;
                }

                call_user_func($this->onPayloadReceived, $payload->value);
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
