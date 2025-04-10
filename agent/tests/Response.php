<?php

namespace Tests;

use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Loop;
use React\Http\Message\Response as ReactResponse;
use React\Http\Message\ResponseException;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use RuntimeException;
use Throwable;

use function is_array;
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
        public ?int $status = 200,
        public int $defer = 0,
    ) {
        //
    }

    public static function jwt(
        string $token = 'NIGHTWATCH_TEST_TOKEN',
        int $expiresIn = 7_200,
        int $refreshIn = 3_600,
        string $ingestUrl = 'https://ingest.nightwatch.laravel.com',
        int $defer = 0,
    ): self {
        return new self([
            'token' => $token,
            'expires_in' => $expiresIn,
            'ingest_url' => $ingestUrl,
            'refresh_in' => $refreshIn,
        ], defer: $defer);
    }

    public static function unauthenticated(
        string $message = 'Invalid environment token',
        int $defer = 0
    ): self {
        return new self(['message' => $message], status: 401, defer: $defer);
    }

    public static function internalServerError(
        string $body = '',
        int $defer = 0,
    ): self {
        return new self($body, status: 500, defer: $defer);
    }

    public static function throwWhileProcessing(
        string|Throwable $e,
        int $defer = 0,
    ): self {
        if (is_string($e)) {
            return new self([RuntimeException::class, $e], status: null, defer: $defer);
        } else {
            return new self([$e::class, $e->getMessage()], status: null, defer: $defer);
        }
    }

    /**
     * @return PromiseInterface<ResponseInterface>
     */
    public function toPromise(): PromiseInterface
    {
        if ($this->defer) {
            $deferred = new Deferred;

            Loop::addTimer($this->defer, function () use ($deferred) {
                $deferred->resolve($this->toResponsePromise());
            });

            return $deferred->promise();
        }

        return $this->toResponsePromise();
    }

    /**
     * @return PromiseInterface<ResponseInterface>
     */
    public function toResponsePromise(): PromiseInterface
    {
        if ($this->status === null && is_array($this->body)) {
            /** @var class-string<Throwable> $class */
            [$class, $message] = $this->body;

            return reject(new $class($message));
        }

        return $this->status >= 400
            ? reject(new ResponseException($this->toResponse()))
            : resolve($this->toResponse());
    }

    public function toResponse(): ReactResponse
    {
        if ($this->status === null) {
            throw new RuntimeException('Status must be an integer.');
        }

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
