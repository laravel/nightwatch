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
    private int $activeRequests = 0;

    /**
     * @param  non-negative-int  $concurrentRequestLimit
     */
    public function __construct(
        private Browser $browser,
        private int $concurrentRequestLimit,
    ) {
        //
    }

    /**
     * @return PromiseInterface<IngestSucceededResult>
     */
    public function write(string $records): PromiseInterface
    {
        if ($this->activeRequests === $this->concurrentRequestLimit) {
            return new RejectedPromise(new ExceededConcurrentRequestLimitException("Exceeded concurrent request limit [{$this->concurrentRequestLimit}]."));
        }

        $this->activeRequests++;

        // TODO: HTTP retry logic in here or the command?
        // TODO gzip
        $start = hrtime(true);

        return $this->browser->post('/', body: $records)
            ->then(function (ResponseInterface $response) use ($start): IngestSucceededResult {
                return new IngestSucceededResult(
                    duration: (hrtime(true) - $start) / 1_000_000,
                );
            }, function (Throwable $e) use ($start): void {
                throw new IngestFailedException(
                    duration: (hrtime(true) - $start) / 1_000_000,
                    previous: $e
                );
            })->finally(function (): void {
                $this->activeRequests--; // @phpstan-ignore assign.propertyType
            });
    }
}
