<?php

namespace Laravel\Nightwatch\Ingests\Remote;

use Psr\Http\Message\ResponseInterface;
use React\Http\Browser;
use React\Promise\Internal\RejectedPromise;
use React\Promise\PromiseInterface;
use RuntimeException;

use function gzencode;

/**
 * @internal
 */
class HttpClient
{
    public function __construct(
        private Browser $browser,
        private string $path,
    ) {
        //
    }

    /**
     * @return PromiseInterface<ResponseInterface>
     */
    public function send(string $payload): PromiseInterface
    {
        // TODO determine what level to allow here.
        $payload = gzencode($payload);

        if ($payload === false) {
            return new RejectedPromise(new RuntimeException('Unable to compress payload.'));
        }

        return $this->browser->post($this->path, body: $payload);
    }
}
