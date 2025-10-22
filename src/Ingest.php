<?php

namespace Laravel\Nightwatch;

use Deprecated;
use Laravel\Nightwatch\Contracts\Ingest as IngestContract;
use RuntimeException;

use function call_user_func;
use function Nightwatch\fclose_safely;
use function Nightwatch\fread_all;
use function Nightwatch\fwrite_all;
use function Nightwatch\stream_configure_read_timeout;

/**
 * @internal
 */
final class Ingest implements IngestContract
{
    private string $transmitTo;

    private bool $shouldDigestWhenBufferIsFull = true;

    /**
     * @param  (callable(string $address, float $timeout): resource)  $streamFactory
     */
    public function __construct(
        string $transmitTo,
        private float $connectionTimeout,
        private float $timeout,
        public $streamFactory,
        public RecordsBuffer $buffer,
        private string $tokenHash,
    ) {
        $this->transmitTo = "tcp://{$transmitTo}";
    }

    public function write(array $record): void
    {
        $this->buffer->write($record);

        if ($this->shouldDigestWhenBufferIsFull && $this->buffer->full) {
            $this->digest();
        }
    }

    public function writeNow(array $record): void
    {
        $this->transmit(Payload::json([$record], $this->tokenHash));
    }

    public function flush(): void
    {
        $this->buffer->flush();
    }

    public function ping(): void
    {
        $this->transmit(Payload::text('PING', $this->tokenHash));
    }

    #[Deprecated('Use shouldDigestWhenBufferIsFull instead')]
    public function shouldDigest(bool $bool = true): void
    {
        $this->shouldDigestWhenBufferIsFull($bool);
    }

    public function shouldDigestWhenBufferIsFull(bool $bool = true): void
    {
        $this->shouldDigestWhenBufferIsFull = $bool;
    }

    public function digest(): void
    {
        $this->transmit($this->buffer->pull($this->tokenHash));
    }

    private function transmit(Payload $payload): void
    {
        if ($payload->isEmpty()) {
            return;
        }

        $stream = $this->createStream();

        try {
            $this->configureStreamTimeout($stream);

            $this->sendPayload($stream, $payload);

            $this->waitForAcknowledgment($stream);
        } finally {
            fclose_safely($stream);
        }
    }

    /**
     * @return resource
     */
    private function createStream()
    {
        return call_user_func($this->streamFactory, $this->transmitTo, $this->connectionTimeout);
    }

    /**
     * @param  resource  $stream
     */
    private function configureStreamTimeout($stream): void
    {
        stream_configure_read_timeout($stream, $this->timeout);
    }

    /**
     * @param  resource  $stream
     */
    private function sendPayload($stream, Payload $payload): void
    {
        fwrite_all($stream, $payload->pull(...));
    }

    /**
     * @param  resource  $stream
     */
    private function waitForAcknowledgment($stream): void
    {
        $response = fread_all($stream, 4);

        if ($response !== '2:OK') {
            throw new RuntimeException("Unexpected response from agent [{$response}]");
        }
    }
}
