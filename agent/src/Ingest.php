<?php

namespace Laravel\NightwatchAgent;

use Closure;
use Laravel\NightwatchAgent\Contracts\Browser;
use Laravel\NightwatchAgent\Contracts\Clock;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Http\Message\ResponseException;
use React\Promise\PromiseInterface;
use RuntimeException;
use Throwable;

use function array_key_last;
use function call_user_func;
use function count;
use function gzencode;
use function json_decode;
use function microtime;
use function React\Promise\all;
use function strlen;
use function substr;

class Ingest
{
    /**
     * @var array<int<0, max>, PromiseInterface<null>>
     */
    private array $concurrentRequests = [];

    private ?TimerInterface $sendBufferAfterDelayTimer = null;

    private StreamBuffer|NullBuffer $buffer;

    private StreamBuffer $streamBufferBackup;

    /**
     * @param  (Closure(ResponseInterface $response, float $duration): mixed)  $onIngestSuccess
     * @param  (Closure(string $message, float $duration): mixed)  $onIngestError
     * @param  (Closure(string $message, float $duration): mixed)  $onOverQuota
     */
    public function __construct(
        private LoopInterface $loop,
        private Browser $browser,
        private IngestDetailsRepository $ingestDetails,
        private Clock $clock,
        StreamBuffer $buffer,
        private int $concurrentRequestLimit,
        private int $maxBufferDurationInSeconds,
        private Closure $onIngestSuccess,
        private Closure $onIngestError,
        private Closure $onOverQuota,
    ) {
        $this->buffer = $this->streamBufferBackup = $buffer;
    }

    public function write(string $payload): void
    {
        $this->buffer->write($payload);

        if ($this->buffer->reachedThreshold()) {
            if ($this->sendBufferAfterDelayTimer !== null) {
                $this->loop->cancelTimer($this->sendBufferAfterDelayTimer);

                $this->sendBufferAfterDelayTimer = null;
            }

            $this->digest();
        } elseif ($this->buffer->isNotEmpty()) {
            $this->sendBufferAfterDelayTimer ??= $this->loop->addTimer($this->maxBufferDurationInSeconds, function () {
                $this->sendBufferAfterDelayTimer = null;

                $this->digest();
            });
        }
    }

    /**
     * @return PromiseInterface<null>
     */
    public function forceDigest(): PromiseInterface
    {
        if ($this->sendBufferAfterDelayTimer !== null) {
            $this->loop->cancelTimer($this->sendBufferAfterDelayTimer);
        }

        if ($this->buffer->isNotEmpty()) {
            $this->digest();
        }

        return all($this->concurrentRequests)->then(static fn () => null);
    }

    public function pauseIngestion(): void
    {
        $this->buffer = new NullBuffer;

        $this->streamBufferBackup->flush();
    }

    public function resumeIngestion(): void
    {
        $this->buffer = $this->streamBufferBackup;
    }

    private function digest(): void
    {
        $payload = $this->buffer->pull();

        if (count($this->concurrentRequests) >= $this->concurrentRequestLimit) {
            call_user_func($this->onIngestError, "Exceeded concurrent request limit. [{$this->concurrentRequestLimit}] requests are processing", 0.0);

            return;
        }

        // TODO determine what level is optimal here
        $payload = gzencode($payload);

        if ($payload === false) {
            call_user_func($this->onIngestError, 'Unable to compress payload.', 0.0);

            return;
        }

        $start = microtime(true);
        $currentRequestKey = (array_key_last($this->concurrentRequests) ?? -1) + 1;

        ($this->concurrentRequests[$currentRequestKey] = $this->ingestDetails->get()->then(function (?IngestDetails $ingestDetails) use ($payload, &$start): PromiseInterface {
            $start = microtime(true);

            if ($ingestDetails === null) {
                throw new RuntimeException('No authentication details');
            }

            if ($this->clock->time() > $ingestDetails->expiresAt) {
                throw new RuntimeException('Authentication token expired');
            }

            return $this->browser->post(
                url: $ingestDetails->ingestUrl,
                headers: [
                    'authorization' => "Bearer {$ingestDetails->token}",
                ],
                body: $payload,
            );
        })->then(function (ResponseInterface $response) use (&$start): null {
            $duration = microtime(true) - $start;

            [$message, $stop, $refreshIn] = $this->parseResponse($response);

            if ($stop) {
                $this->stop($this->onOverQuota, $duration, $message, $refreshIn);
            } else {
                call_user_func($this->onIngestSuccess, $response, microtime(true) - $start);
            }

            return null;
        })->catch(function (Throwable $e) use (&$start): null {
            $duration = microtime(true) - $start;

            [$message, $stop, $refreshIn] = $this->parseException($e);

            if ($stop) {
                $this->stop($this->onIngestError, $duration, $message, $refreshIn);
            } else {
                call_user_func($this->onIngestError, $message, $duration);
            }

            return null;
        }))->finally(function () use ($currentRequestKey): void {
            unset($this->concurrentRequests[$currentRequestKey]);
        });
    }

    private function stop(callable $errorHandler, float $duration, string $message, float|int|null $refreshIn = null): void
    {
        $this->pauseIngestion();

        $this->ingestDetails->markOverQuota($refreshIn);

        call_user_func($errorHandler, $message, $duration);
    }

    /**
     * @return array{0: string, 1: bool, 2: null|int|float}
     */
    private function parseException(Throwable $e): array
    {
        return $e instanceof ResponseException
            ? $this->parseResponse($e->getResponse())
            : [$e->getMessage(), false, null];
    }

    /**
     * @return array{0: string, 1: bool, 2: null|int|float}
     */
    private function parseResponse(ResponseInterface $response): array
    {
        $message = (string) $response->getBody();
        $stop = false;
        $refreshIn = null;

        try {
            /** @var array{ message?: string, refresh_in?: int|float, stop?: bool } $json */
            $json = json_decode($message, associative: true, flags: JSON_THROW_ON_ERROR);

            $message = $json['message'] ?? $message;
            $stop = $json['stop'] ?? $stop;
            $refreshIn = $json['refresh_in'] ?? $refreshIn;
        } catch (Throwable $exception) {
            //
        }

        if (strlen($message) > 1005) {
            $message = substr($message, 0, 1000).'[...]';
        }

        return [
            "{$response->getStatusCode()} [{$message}]",
            $stop,
            $refreshIn,
        ];
    }
}
