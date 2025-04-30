<?php

namespace Laravel\Nightwatch;

use const STREAM_CLIENT_CONNECT;

use Laravel\Nightwatch\Contracts\LocalIngest;
use RuntimeException;
use Throwable;

use function fclose;
use function feof;
use function fread;
use function fwrite;
use function gettype;
use function intval;
use function stream_get_meta_data;
use function stream_set_timeout;
use function stream_socket_client;
use function strlen;
use function substr;

/**
 * @internal
 */
final class Ingest implements LocalIngest
{
    private string $transmitTo;

    /**
     * @var array{seconds: int, microseconds: int}
     */
    private array $ingestTimeout;

    public function __construct(
        string $transmitTo,
        float $ingestTimeout,
        private float $ingestConnectionTimeout,
    ) {
        $this->transmitTo = "tcp://{$transmitTo}";

        $this->ingestTimeout = [
            'seconds' => $seconds = (int) $ingestTimeout,
            'microseconds' => intval(($ingestTimeout - $seconds) * 1_000_000),
        ];
    }

    public function write(string $payload): void
    {
        if ($payload === '[]') {
            return;
        }

        $this->ingest($payload);
    }

    public function ping(): bool
    {
        $response = $this->ingest('PING');

        if ($response === '4:PONG') {
            return true;
        }

        throw new RuntimeException("Unexpected response from agent: [{$response}]");
    }

    private function ingest(string $payload): string
    {
        $stream = $this->createStream();

        // The payload is potentially a massive string. Let's say it is 1MB.
        // You might be tempted to concatenate these two strings and only write
        // to the stream once, however that would create a new 1MB+ string in
        // memory and potentially overflow PHP's memory allowence. In order to
        // reserve memory, we write the individual strings to the stream as
        // different writes. Slight performance trade off in order to keep
        // memory usage low.
        $this->writeToStream($stream, strlen($payload).':');
        $this->writeToStream($stream, $payload);

        $response = $this->readFromStream($stream);

        $this->closeStream($stream);

        return $response;
    }

    /**
     * @return resource
     */
    private function createStream()
    {
        $stream = stream_socket_client(
            address: $this->transmitTo,
            error_code: $errorCode,
            error_message: $errorMessage,
            timeout: $this->ingestConnectionTimeout,
            flags: STREAM_CLIENT_CONNECT,
        );

        if ($stream === false) {
            throw new RuntimeException("Failed connecting to the agent: {$errorMessage} [{$errorCode}]");
        }

        $timeoutConfigured = stream_set_timeout(
            $stream,
            $this->ingestTimeout['seconds'],
            $this->ingestTimeout['microseconds'],
        );

        if ($timeoutConfigured === false) {
            $this->closeStreamAfterError('Failed configuring agent writing timeout', $stream);
        }

        return $stream;
    }

    /**
     * @param  resource  $stream
     */
    private function writeToStream($stream, string $payload): void
    {
        $written = 0;
        $remainingPayload = $payload;
        $payloadLength = strlen($payload);

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
    private function readFromStream($stream): string
    {
        $response = '';

        // We are expecting a 4-byte response of "2:OK"...
        do {
            $part = fread($stream, 4);

            if ($part === false) {
                $this->closeStreamAfterError('Failed reading from the agent', $stream);
            }

            $response .= $part;
        } while (strlen($response) < 4 && ! feof($stream));

        return $response;
    }

    /**
     * @param  resource  $stream
     */
    private function closeStreamAfterError(string $message, $stream): never
    {
        $meta = stream_get_meta_data($stream);

        $uri = $meta['uri'] ?? '';
        $timedOut = $meta['timed_out'] ? 'true' : 'false';
        $eof = $meta['eof'] ? 'true' : 'false';
        $blocked = $meta['blocked'] ? 'true' : 'false';

        $this->closeStream($stream, new RuntimeException($message.<<<MESSAGE


            Timed out: {$timedOut}
            EOF: {$timedOut}
            Blocked: {$blocked}
            URI: {$uri}
            Unread bytes: {$meta['unread_bytes']}
            MESSAGE));
    }

    /**
     * @param  resource  $stream
     * @return ($previous is null ? void : never)
     */
    private function closeStream($stream, ?Throwable $previous = null): void
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
