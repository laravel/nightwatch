<?php

namespace Laravel\Nightwatch;

use Psr\Http\Message\ResponseInterface;
use React\Http\Browser;
use React\Promise\Internal\RejectedPromise;
use React\Promise\PromiseInterface;
use RuntimeException;

final class Client
{
    public function __construct(private Browser $browser)
    {
        //
    }

    /**
     * @return PromiseInterface<ResponseInterface>
     */
    public function send(string $payload): PromiseInterface
    {
        $payload = gzencode($payload);

        if ($payload === false) {
            return new RejectedPromise(new RuntimeException('Unable to compress payload'));
        }

        return $this->browser->post('/nightwatch-ingest', body: $payload);
    }
}
