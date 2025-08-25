<?php

namespace Laravel\Nightwatch;

use Laravel\Nightwatch\Contracts\Ingest as IngestContract;
use RuntimeException;
use Throwable;

use function call_user_func;
use function fclose;
use function feof;
use function fread;
use function fwrite;
use function gettype;
use function intval;
use function stream_get_meta_data;
use function stream_set_timeout;
use function strlen;
use function substr;

/**
 * @internal
 */
final class Ingest implements IngestContract
{
    private string $transmitTo;

    /**
     * @var array{seconds: int, microseconds: int}
     */
    private array $timeout;

    private bool $shouldDigest = true;

    /**
     * @param  (callable(string $address, float $timeout): resource)  $streamFactory
     */
    public function __construct(
        string $transmitTo,
        private float $connectionTimeout,
        float $timeout,
        public $streamFactory,
        public RecordsBuffer $buffer,
        private string $tokenHash,
    ) {
        $this->transmitTo = "tcp://{$transmitTo}";

        $this->timeout = [
            'seconds' => $seconds = (int) $timeout,
            'microseconds' => intval(($timeout - $seconds) * 1_000_000),
        ];
    }

    public function write(array $record): void
    {
        $this->buffer->write($record);

        if ($this->shouldDigest && $this->buffer->full) {
            $this->digest();
        }
    }

    public function writeNow(array $record): void
    {
        if (! $this->shouldDigest) {
            return;
        }

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

    public function shouldDigest(bool $bool): void
    {
        $this->shouldDigest = $bool;
    }

    public function digest(): void
    {
        if ($this->shouldDigest) {
            $this->transmit($this->buffer->pull($this->tokenHash));
        } else {
            $this->flush();
        }
    }

    private function transmit(Payload $payload): void
    {
        if ($payload->isEmpty()) {
            return;
        }

        $stream = $this->createStream();

        $this->sendPayload($stream, $payload);

        $this->waitForAcknowledgment($stream);

        $this->close($stream);
    }

    /**
     * @return resource
     */
    private function createStream()
    {
        $stream = call_user_func($this->streamFactory, $this->transmitTo, $this->connectionTimeout);

        $timeoutConfigured = stream_set_timeout(
            $stream,
            $this->timeout['seconds'],
            $this->timeout['microseconds'],
        );

        if ($timeoutConfigured === false) {
            $this->closeStreamAfterError('Failed configuring agent read timeout', $stream);
        }

        return $stream;
    }

    /**
     * @param  resource  $stream
     */
    private function sendPayload($stream, Payload $payload): void
    {
        $written = 0;
        $remainingPayload = $payload->pull();
        $payloadLength = strlen($remainingPayload);

        while (true) {
            $thisWrite = fwrite($stream, $remainingPayload);

            if ($thisWrite === false) {
                $this->closeStreamAfterError("Unable to write to the agent. Written [{$written}] Expected [{$payloadLength}]", $stream);
            }

            $written += $thisWrite;

            if ($written >= $payloadLength) {
                return;
            }

            $remainingPayload = substr($remainingPayload, $thisWrite);
        }
    }

    /**
     * @param  resource  $stream
     */
    private function waitForAcknowledgment($stream): void
    {
        $response = '';
        $attempts = 0;

        do {
            // We are expecting a 4-byte response of "2:OK"...
            $part = fread($stream, 4);

            if ($part === false) {
                $this->closeStreamAfterError('Failed reading from the agent', $stream);
            }

            $response .= $part;
            $attempts++;
        } while (strlen($response) < 4 && ! feof($stream) && $attempts < 5);

        if ($response !== '2:OK') {
            $this->closeStreamAfterError("Unexpected response from agent [{$response}]", $stream);
        }
    }

    /**
     * @param  resource  $stream
     */
    private function closeStreamAfterError(string $message, $stream): never
    {
        if ($this->closed($stream)) {
            throw new RuntimeException($message.<<<'MESSAGE'


            Stream already closed
            MESSAGE);
        }

        $meta = stream_get_meta_data($stream);

        $uri = $meta['uri'] ?? '';
        $timedOut = $meta['timed_out'] ? 'true' : 'false';
        $eof = $meta['eof'] ? 'true' : 'false';
        $blocked = $meta['blocked'] ? 'true' : 'false';

        $this->close($stream, new RuntimeException($message.<<<MESSAGE


            Timed out: {$timedOut}
            EOF: {$eof}
            Blocked: {$blocked}
            URI: {$uri}
            Unread bytes: {$meta['unread_bytes']}
            MESSAGE));
    }

    /**
     * @param  resource  $stream
     * @return ($previous is null ? void : never)
     */
    private function close($stream, ?Throwable $previous = null): void
    {
        if (! $this->closed($stream) && fclose($stream) === false) {
            throw new RuntimeException('Unable to close connection to agent', previous: $previous);
        }

        if ($previous !== null) {
            throw $previous;
        }
    }

    /**
     * @param  resource  $stream
     */
    private function closed($stream): bool
    {
        return gettype($stream) === 'resource (closed)';
    }
}
