<?php

namespace Laravel\Nightwatch\Exceptions;

use React\Http\Message\ResponseException;
use RuntimeException;
use Throwable;

final class IngestFailedException extends RuntimeException
{
    public ?string $response;

    public function __construct(
        public int $duration,
        Throwable $previous,
    ) {
        $message = $previous instanceof ResponseException
            ? (string) $previous->getResponse()->getBody()
            : 'Unknown ingest error.';

        parent::__construct($message, previous: $previous);
    }
}
