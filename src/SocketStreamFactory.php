<?php

namespace Laravel\Nightwatch;

use RuntimeException;

use function stream_socket_client;

/**
 * @internal
 */
final class SocketStreamFactory
{
    /**
     * @return resource
     */
    public function __invoke(
        string $address,
        float $connectionTimeout,
    ) {
        $stream = stream_socket_client(
            address: $address,
            error_code: $errorCode,
            error_message: $errorMessage,
            timeout: $connectionTimeout,
        );

        if ($stream === false) {
            throw new RuntimeException("Failed connecting to the agent: {$errorMessage} [{$errorCode}]");
        }

        return $stream;
    }
}
