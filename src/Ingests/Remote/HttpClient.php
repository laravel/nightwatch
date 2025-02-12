<?php

namespace Laravel\Nightwatch\Ingests\Remote;

use Laravel\Nightwatch\IngestDetailsRepository;
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
        private IngestDetailsRepository $ingestDetails,
    ) {
        //
    }

    /**
     * @return PromiseInterface<ResponseInterface>
     */
    public function send(string $payload): PromiseInterface
    {
        $details = $this->ingestDetails->get();

        if ($details === null) {
            return new RejectedPromise(new RuntimeException('Agent is not authenticated.'));
        }

        // TODO determine what level to allow here.
        $payload = gzencode($payload);

        if ($payload === false) {
            return new RejectedPromise(new RuntimeException('Unable to compress payload.'));
        }

        return $this->browser->post($details->ingestUrl, headers: [
            'authorization' => "Bearer {$details->token}",
        ], body: $payload);
    }
}
