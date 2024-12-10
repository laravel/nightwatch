<?php

namespace Laravel\Nightwatch\Ingests\Remote;

use Psr\Http\Message\ResponseInterface;
use React\Http\Message\ResponseException;
use RuntimeException;
use Throwable;

/**
 * @internal
 */
final class IngestFailedException extends RuntimeException
{
    public ?ResponseInterface $response;

    public function __construct(public float $duration, Throwable $previous)
    {
        if ($previous instanceof ResponseException) {
            $this->response = $previous->getResponse();

            $message = (string) $this->response->getBody();
        } else {
            $message = $previous->getMessage();
        }

        parent::__construct("Took [{$this->duration}]s. {$message}", previous: $previous);
    }
}
