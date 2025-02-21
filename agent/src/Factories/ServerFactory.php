<?php

namespace Laravel\NightwatchAgent\Factories;

use Closure;
use Laravel\NightwatchAgent\Server;
use React\Socket\TcpServer;
use Throwable;

class ServerFactory
{
    /**
     * @param  (Closure(): mixed)  $onServerStarted
     * @param  (Closure(Throwable $e): mixed)  $onServerError
     * @param  (Closure(Throwable $e): mixed)  $onConnectionError
     * @param  (Closure(string $payload): mixed)  $onPayloadReceived
     */
    public function __invoke(
        string $listenOn,
        Closure $onServerStarted,
        Closure $onServerError,
        Closure $onConnectionError,
        Closure $onPayloadReceived,
    ): Server {
        return new Server(
            serverResolver: static fn (): TcpServer => new TcpServer($listenOn),
            onServerStarted: $onServerStarted,
            onServerError: $onServerError,
            onConnectionError: $onConnectionError,
            onPayloadReceived: $onPayloadReceived,
        );
    }
}
