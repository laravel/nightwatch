<?php

namespace Laravel\Nightwatch\Ingests\Remote;

use Laravel\Nightwatch\Client;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Exceptions\ExceededConcurrentRequestLimitException;
use Laravel\Nightwatch\Exceptions\IngestFailedException;
use Laravel\Nightwatch\IngestSucceededResult;
use Psr\Http\Message\ResponseInterface;
use React\Promise\Internal\RejectedPromise;
use React\Promise\PromiseInterface;
use Throwable;

use function round;

/**
 * @internal
 */
final class HttpIngest
{
    private int $concurrentRequests = 0;

    public function __construct(
        private Client $client,
        private Clock $clock,
        private int $concurrentRequestLimit,
    ) {
        //
    }

    /**
     * TODO retry logic
     *
     * @return PromiseInterface<IngestSucceededResult>
     */
    public function write(string $payload): PromiseInterface
    {
        if ($this->concurrentRequests === $this->concurrentRequestLimit) {
            return new RejectedPromise(
                new ExceededConcurrentRequestLimitException("Exceeded concurrent request limit [{$this->concurrentRequestLimit}].")
            );
        }

        $this->concurrentRequests++;

        $start = $this->clock->microtime();

        return $this->client->send($payload)
            ->then(fn (ResponseInterface $response) => new IngestSucceededResult(
                duration: (int) round($this->clock->diffInMicrotime($start) * 1000),
            ), fn (Throwable $e) => throw new IngestFailedException(
                duration: (int) round($this->clock->diffInMicrotime($start) * 1000),
                previous: $e
            ))->finally(function () {
                $this->concurrentRequests--;
            });
    }

    public function concurrentRequests(): int
    {
        return $this->concurrentRequests;
    }
}
