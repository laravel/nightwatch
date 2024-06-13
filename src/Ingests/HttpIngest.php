<?php

namespace Laravel\Nightwatch\Ingests;

use Laravel\Nightwatch\Client;
use Laravel\Nightwatch\Contracts\Clock;
use Laravel\Nightwatch\Exceptions\ExceededConcurrentRequestLimitException;
use Laravel\Nightwatch\Exceptions\IngestFailedException;
use Laravel\Nightwatch\IngestSucceededResult;
use Psr\Http\Message\ResponseInterface;
use React\Http\Browser;
use React\Promise\Internal\RejectedPromise;
use React\Promise\PromiseInterface;
use Throwable;

final class HttpIngest
{
    /**
     * @var non-negative-int
     */
    private int $concurrentRequests = 0;

    /**
     * @param  non-negative-int  $concurrentRequestLimit
     */
    public function __construct(
        private Client $client,
        private Clock $clock,
        private int $concurrentRequestLimit,
    ) {
        //
    }

    /**
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

        $payload = gzencode($payload);
        $start = $this->clock->microtime();

        // TODO: retry logic
        return $this->client->send($payload)
            ->then(fn (ResponseInterface $response) => new IngestSucceededResult(
                duration: round($this->clock->diffInMicrotime($start) * 1000),
            ), fn (Throwable $e) => throw new IngestFailedException(
                duration: round($this->diffInMicrotime($start) * 1000),
                previous: $e
            ))->finally(function () {
                $this->concurrentRequests--; // @phpstan-ignore assign.propertyType
            });
    }

    /**
     * @return non-negative-int
     */
    public function concurrentRequests(): int
    {
        return $this->concurrentRequests;
    }
}
