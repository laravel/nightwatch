<?php

namespace Laravel\Nightwatch\Exceptions;

use Psr\Http\Message\ResponseInterface;
use React\Http\Message\ResponseException;
use RuntimeException;
use Throwable;

final class IngestFailedException extends RuntimeException
{
    public ?ResponseInterface $response;

    public function __construct(public int $duration, Throwable $previous)
    {
        if ($previous instanceof ResponseException) {
            $this->response = $previous->getResponse();

            $message = (string) $this->response->getBody();
        } else {
            $message = 'Unknown ingest error.';
        }

        parent::__construct($message, previous: $previous);
    }
}
