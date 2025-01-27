<?php

namespace Laravel\Nightwatch;

use Psr\Http\Message\ResponseInterface;
use React\Http\Browser;
use React\Promise\PromiseInterface;
use RuntimeException;
use Throwable;

use function is_array;
use function json_decode;

final class IngestDetailsRepository
{
    private ?IngestDetails $ingestDetails = null;

    public function __construct(
        private Browser $browser,
    ) {
        //
    }

    public function get(): ?IngestDetails
    {
        return $this->ingestDetails;
    }

    /**
     * @return PromiseInterface<IngestDetails>
     */
    public function refresh(): PromiseInterface
    {
        return $this->browser->post('')->then(function (ResponseInterface $response) {
            $data = json_decode($response->getBody()->getContents(), true, flags: JSON_THROW_ON_ERROR);

            if (
                ! is_array($data) ||
                ! isset($data['token'], $data['expires_in'], $data['ingest_url']) ||
                ! $data['token'] ||
                ! $data['expires_in'] ||
                ! $data['ingest_url']
            ) {
                throw new RuntimeException("Invalid authentication response: [{$response->getBody()->getContents()}]");
            }

            return $this->ingestDetails = new IngestDetails($data['token'], $data['expires_in'], $data['ingest_url']);
        })->catch(function (Throwable $e) {
            $this->ingestDetails = null;

            throw $e;
        });
    }
}
