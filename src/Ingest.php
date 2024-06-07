<?php

namespace Laravel\Nightwatch;

use Laravel\Nightwatch\Exceptions\ExceededConcurrentRequestLimitException;
use Psr\Http\Message\ResponseInterface;
use React\Http\Browser;
use React\Promise\Internal\RejectedPromise;
use React\Promise\PromiseInterface;
use Throwable;

final class Ingest
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
        private int $concurrentRequestLimit,
    ) {
        //
    }

    /**
     * @return PromiseInterface<IngestSucceededResult>
     */
    public function write(string $records): PromiseInterface
    {
        if ($this->concurrentRequests === $this->concurrentRequestLimit) {
            return new RejectedPromise(new ExceededConcurrentRequestLimitException("Exceeded concurrent request limit [{$this->concurrentRequestLimit}]."));
        }

        $this->concurrentRequests++;

        // TODO: HTTP retry logic in here or the command?
        // TODO gzip
        $start = hrtime(true);

        return $this->client->send($records)
            ->then(function (ResponseInterface $response) use ($start) {
                return new IngestSucceededResult(
                    duration: (hrtime(true) - $start) / 1_000_000,
                );
            }, function (Throwable $e) use ($start) {
                throw new IngestFailedException(
                    duration: (hrtime(true) - $start) / 1_000_000,
                    previous: $e
                );
            })->finally(function () {
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
