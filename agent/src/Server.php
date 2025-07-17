<?php

namespace Laravel\NightwatchAgent;

use Closure;
use React\Socket\ConnectionInterface;
use React\Socket\ServerInterface;
use Throwable;

use function call_user_func;

class Server
{
    /**
     * @param  (Closure(): ServerInterface)  $serverResolver
     * @param  (Closure(): mixed)  $onServerStarted
     * @param  (Closure(string $message): mixed)  $onServerError
     * @param  (Closure(string $message): mixed)  $onConnectionError
     * @param  (Closure(string $payload): mixed)  $onPayloadReceived
     * @param  (Closure(): mixed)  $onInvalidPayloadVersion
     * @param  (Closure(): mixed)  $onInvalidTokenHash
     */
    public function __construct(
        private Closure $serverResolver,
        private string $tokenHash,
        private Closure $onServerStarted,
        private Closure $onServerError,
        private Closure $onConnectionError,
        private Closure $onPayloadReceived,
        private Closure $onInvalidPayloadVersion,
        private Closure $onInvalidTokenHash,
    ) {
        //
    }

    public function start(): void
    {
        $server = call_user_func($this->serverResolver);

        $server->on('connection', function (ConnectionInterface $connection) use ($server): void {
            $payload = new Payload;

            $connection->on('data', static function (string $chunk) use ($connection, $payload): void {
                $payload->append($chunk);

                if (! $payload->complete) {
                    return;
                }

                $connection->end('2:OK');
            });

            $connection->on('close', function () use ($server, $payload) {
                if (! $payload->complete) {
                    call_user_func($this->onConnectionError, "Incomplete payload received. Length: [{$payload->length}] Value: [{$payload->value}]");

                    return;
                }

                if (! $payload->versionIsValid()) {
                    $server->close();

                    call_user_func($this->onInvalidPayloadVersion);

                    return;
                }

                if ($payload->tokenHash !== $this->tokenHash) {
                    call_user_func($this->onInvalidTokenHash);

                    return;
                }

                if ($payload->value === 'PING') {
                    return;
                }

                call_user_func($this->onPayloadReceived, $payload->value);
            });

            $connection->on('error', function (Throwable $e): void {
                call_user_func($this->onConnectionError, $e->getMessage());
            });
        });

        $server->on('error', function (Throwable $e): void {
            call_user_func($this->onServerError, $e);
        });

        call_user_func($this->onServerStarted);
    }
}
