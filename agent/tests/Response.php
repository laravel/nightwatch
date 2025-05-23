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
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public string|array $body = '',
        public ?int $status = 200,
        public int $duration = 0,
        public array $headers = [],
    ) {
        //
    }

    /**
     * @param  array<string, string>  $headers
     */
    public static function jwt(
        string $token = 'NIGHTWATCH_TEST_TOKEN',
        int $expiresIn = 7_200,
        int $refreshIn = 3_600,
        string $ingestUrl = 'https://ingest.nightwatch.laravel.com',
        int $duration = 0,
        array $headers = ['Content-Type' => 'application/json'],
    ): self {
        return new self([
            'token' => $token,
            'expires_in' => $expiresIn,
            'ingest_url' => $ingestUrl,
            'refresh_in' => $refreshIn,
        ], duration: $duration, headers: $headers);
    }

    /**
     * @param  array<mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public static function unauthenticated(
        array $payload = [
            'message' => 'Invalid environment token',
            'refresh_in' => 3_600,
        ],
        int $duration = 0,
        array $headers = ['Content-Type' => 'application/json'],
    ): self {
        return new self($payload, status: 401, duration: $duration, headers: $headers);
    }

    /**
     * @param  array<mixed>  $body
     * @param  array<string, string>  $headers
     */
    public static function ingested(
        string|array $body = '{}',
        ?int $status = 200,
        int $duration = 0,
        array $headers = [],
    ): self {
        return new self($body, $status, $duration, $headers);
    }

    /**
     * @param  array<string, string>  $headers
     */
    public static function internalServerError(
        string $body = '',
        int $duration = 0,
        array $headers = [],
    ): self {
        return new self($body, status: 500, duration: $duration, headers: $headers);
    }

    public static function throwWhileProcessing(
        string|Throwable $e,
        int $duration = 0,
    ): self {
        if (is_string($e)) {
            return new self([RuntimeException::class, $e], status: null, duration: $duration);
        } else {
            return new self([$e::class, $e->getMessage()], status: null, duration: $duration);
        }
    }

    /**
     * @param  array<string, string>  $headers
     */
    public static function ingest(
        int $remaining = 100_000,
        int $duration = 0,
        array $headers = ['Content-Type' => 'application/json'],
    ): self {
        return new self(['remaining' => $remaining], duration: $duration, headers: $headers);
    }

    /**
     * @return PromiseInterface<ResponseInterface>
     */
    public function toPromise(): PromiseInterface
    {
        if ($this->duration) {
            /** @var Deferred<ResponseInterface> $deferred */
            $deferred = new Deferred;

            $loop = Loop::get();
            $loop->addTimer($this->duration, function () use ($deferred) {
                $this->toResponsePromise()->then(function ($response) use ($deferred) {
                    $deferred->resolve($response);
                }, function (Throwable $e) use ($deferred) {
                    $deferred->reject($e);
                });
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
            headers: $this->headers,
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
