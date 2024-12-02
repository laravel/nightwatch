<?php

namespace Laravel\Nightwatch\Ingests\Remote;

use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Contracts\RemoteIngest;
use Psr\Http\Message\ResponseInterface;
use React\Promise\Internal\RejectedPromise;
use React\Promise\PromiseInterface;
use RuntimeException;
use Throwable;

/**
 * @internal
 */
final class HttpIngest implements RemoteIngest
{
    private int $concurrentRequests = 0;

    public function __construct(
        private HttpClient $client,
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
        if ($this->concurrentRequests >= $this->concurrentRequestLimit) {
            return new RejectedPromise(
                new RuntimeException("Exceeded concurrent request limit [{$this->concurrentRequestLimit}].")
            );
        }

        $this->concurrentRequests++;

        $start = $this->clock->microtime();

        return $this->client->send($payload)
            ->then(fn (ResponseInterface $response) => new IngestSucceededResult(
                duration: $this->clock->diffInMicrotime($start),
            ), fn (Throwable $e) => throw new IngestFailedException(
                duration: $this->clock->diffInMicrotime($start),
                previous: $e
            ))->finally(function () {
                $this->concurrentRequests--;
            });
    }
}
