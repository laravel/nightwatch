<?php

namespace Laravel\Package;

use Exception;
use Psr\Http\Message\ResponseInterface;
use React\Http\Browser;
use React\Http\Message\ResponseException;
use React\Promise\Internal\RejectedPromise;
use React\Promise\Promise;
use React\Promise\PromiseInterface;

use function React\Promise\reject;

class Ingest
{
    /** @var non-negative-int */
    private int $activeRequests = 0;

    /** @param  non-negative-int $concurrentRequestLimit */
    public function __construct(
        private Browser $browser,
        private int $concurrentRequestLimit,
    ) {
        //
    }

    /** @return PromiseInterface<null> */
    public function flush(Buffer $buffer): PromiseInterface
    {
        if ($this->activeRequests === $this->concurrentRequestLimit) {
            return new RejectedPromise(new Exception('Whoops!'));
        }

        $this->activeRequests++;

        // TODO gzip
        return $this->browser->post('/', [ // todo: confirm a single browser can handle concurrent requests.

            // 'Content-type: application/octet-stream',
            // 'Content-encoding: gzip',
            // TODO AUTH
            'Content-Type' => 'application/json',
            'Nightwatch-App-Id' => 'TODO',
        ], $buffer->flush())->then(function (ResponseInterface $response): null {
            // $this->line("Recieved response: ".$response->getBody());
            return null;
        }, function (Throwable $e): void {
            // logging. reporting.
            if ($e instanceof ResponseException) {
                // ...
            }
            // $this->line("HTTP request failed with message [{$e->getMessage()}].");
        })->finally(function () { // use ($start) {
            // $diff = (int) ((microtime(true) - $start) * 1000);
            // $this->line("Finished sending request after {$diff} ms.");
            $this->activeRequests--;
        });
    }
}
