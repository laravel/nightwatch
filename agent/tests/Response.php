<?php

namespace Tests;

use Psr\Http\Message\ResponseInterface;
use React\Http\Message\Response as ReactResponse;
use React\Http\Message\ResponseException;
use React\Promise\PromiseInterface;

use function is_string;
use function json_encode;
use function React\Promise\reject;
use function React\Promise\resolve;

class Response
{
    /**
     * @param  string|array<mixed>  $body
     */
    public function __construct(
        public string|array $body = '',
        public int $status = 200,
    ) {
        //
    }

    public static function jwt(
        string $token = 'TOKEN',
        int $expiresIn = 7_200,
        int $refreshIn = 3_600,
        string $ingestUrl = 'https://ingest.nightwatch.laravel.com',
    ): self {
        return new self([
            'token' => $token,
            'expires_in' => $expiresIn,
            'ingest_url' => $ingestUrl,
            'refresh_in' => $refreshIn,
        ]);
    }

    public static function unauthenticated(): self
    {
        return new self([
            'message' => 'Invalid environment token',
        ], status: 401);
    }

    /**
     * @return PromiseInterface<ResponseInterface>
     */
    public function toPromise(): PromiseInterface
    {
        return $this->status >= 400
            ? reject(new ResponseException($this->toResponse()))
            : resolve($this->toResponse());
    }

    public function toResponse(): ReactResponse
    {
        return new ReactResponse(
            status: $this->status,
            body: $this->body(),
        );
    }

    public function body(): string
    {
        return is_string($this->body)
            ? $this->body
            : json_encode($this->body, flags: JSON_THROW_ON_ERROR);
    }
}
