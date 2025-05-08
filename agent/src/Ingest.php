<?php

namespace Laravel\NightwatchAgent;

use Closure;
use Laravel\NightwatchAgent\Contracts\Browser;
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
     * @param  LoopInterface  $loop
     * @param  Browser  $browser
     * @param  (Closure(ResponseInterface $response, float $duration): mixed)  $onIngestSuccess
     * @param  (Closure(Throwable $e, float $duration): mixed)  $onIngestError
     * @param  (Closure(float $duration): mixed)  $onOverQuota
     */
    public function __construct(
        private $loop,
        private $browser,
        private IngestDetailsRepository $ingestDetails,
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
            call_user_func($this->onIngestError, new RuntimeException("Exceeded concurrent request limit. [{$this->concurrentRequestLimit}] requests are processing"), 0.0);

            return;
        }

        // TODO determine what level is optimal here
        $payload = gzencode($payload);

        if ($payload === false) {
            call_user_func($this->onIngestError, new RuntimeException('Unable to compress payload.'), 0.0);

            return;
        }

        $start = microtime(true);
        $currentRequestKey = (array_key_last($this->concurrentRequests) ?? -1) + 1;

        ($this->concurrentRequests[$currentRequestKey] = $this->ingestDetails->get()->then(function (?IngestDetails $ingestDetails) use ($payload, &$start): PromiseInterface {
            $start = microtime(true);

            if ($ingestDetails === null) {
                throw new RuntimeException('No authentication details');
            }

            return $this->browser->post(
                url: $ingestDetails->ingestUrl,
                headers: [
                    'authorization' => "Bearer {$ingestDetails->token}",
                ],
                body: $payload,
            );
        })->then(function (ResponseInterface $response) use (&$start): null {
            /** @var array{remaining: int} */
            $content = json_decode($response->getBody()->getContents(), associative: true, flags: JSON_THROW_ON_ERROR);

            if ($content['remaining'] <= 0) {
                $this->pauseIngestion();
                $this->ingestDetails->markOverQuota();

                call_user_func($this->onOverQuota, microtime(true) - $start);

                return null;
            }

            call_user_func($this->onIngestSuccess, $response, microtime(true) - $start);

            return null;
        })->catch(function (Throwable $e) use (&$start): null {
            call_user_func($this->onIngestError, $this->parseException($e), microtime(true) - $start);

            return null;
        }))->finally(function () use ($currentRequestKey): void {
            unset($this->concurrentRequests[$currentRequestKey]);
        });
    }

    private function parseException(Throwable $e): Throwable
    {
        return $e instanceof ResponseException
            ? $this->parseResponseException($e)
            : $e;
    }

    private function parseResponseException(ResponseException $e): Throwable
    {
        $body = $e->getResponse()->getBody()->getContents();

        if (strlen($body) > 255) {
            $body = substr($body, 0, 250).'[...]';
        }

        return new RuntimeException("{$e->getResponse()->getStatusCode()} [{$body}]");
    }
}
