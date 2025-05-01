<?php

namespace Laravel\Nightwatch;

use RuntimeException;

use function stream_socket_client;

final class SocketStreamFactory
{
    /**
     * @return resource
     */
    public function __invoke(
        string $address,
        float $timeout,
    ) {
        $stream = stream_socket_client(
            address: $address,
            error_code: $errorCode,
            error_message: $errorMessage,
            timeout: $timeout,
        );

        if ($stream === false) {
            throw new RuntimeException("Failed connecting to the agent: {$errorMessage} [{$errorCode}]");
        }

        return $stream;
    }
}
