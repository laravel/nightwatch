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

use function call_user_func;
use function gzencode;
use function json_decode;
use function microtime;
use function React\Promise\reject;
use function strlen;
use function substr;

class Ingest
{
    private int $concurrentRequests = 0;

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
            // TODO track all to ensure every active request has resolved
            // before stopping
            $this->digest();
        } elseif ($this->buffer->isNotEmpty()) {
            $this->sendBufferAfterDelayTimer ??= $this->loop->addTimer($this->maxBufferDurationInSeconds, $this->digest(...));
        }
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

    /**
     * @return PromiseInterface<null>
     */
    public function digest(): PromiseInterface
    {
        if ($this->sendBufferAfterDelayTimer !== null) {
            $this->loop->cancelTimer($this->sendBufferAfterDelayTimer);

            $this->sendBufferAfterDelayTimer = null;
        }

        $payload = $this->buffer->pull();

        if ($this->concurrentRequests >= $this->concurrentRequestLimit) {
            call_user_func($this->onIngestError, $e = new RuntimeException("Exceeded concurrent request limit. [{$this->concurrentRequestLimit}] requests are processing"), 0.0);

            return reject($e);
        }

        // TODO determine what level is optimal here
        $payload = gzencode($payload);

        if ($payload === false) {
            call_user_func($this->onIngestError, $e = new RuntimeException('Unable to compress payload.'), 0.0);

            return reject($e);
        }

        $this->concurrentRequests++;
        $start = microtime(true);

        return $this->ingestDetails->get()->then(function (?IngestDetails $ingestDetails) use ($payload, &$start): PromiseInterface {
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
        })->finally(function (): void {
            $this->concurrentRequests--;
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
