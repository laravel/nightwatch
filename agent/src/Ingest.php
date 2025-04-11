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
use function strlen;
use function substr;

class Ingest
{
    private int $concurrentRequests = 0;

    private ?TimerInterface $flushBufferAfterDelayTimer = null;

    /**
     * @param  LoopInterface  $loop
     * @param  Browser  $browser
     * @param  (Closure(ResponseInterface $response, float $duration): mixed)  $onIngestSuccess
     * @param  (Closure(Throwable $e, float $duration): mixed)  $onIngestError
     * @param  (Closure(float $duration): mixed)  $onExceededQuota
     */
    public function __construct(
        private $loop,
        private $browser,
        private IngestDetailsRepository $ingestDetails,
        private StreamBuffer $buffer,
        private int $concurrentRequestLimit,
        private int $maxBufferDurationInSeconds,
        private Closure $onIngestSuccess,
        private Closure $onIngestError,
        private Closure $onExceededQuota,
    ) {
        //
    }

    public function write(string $payload): void
    {
        if ($this->ingestDetails->quotaExceeded) {
            return;
        }

        $this->buffer->write($payload);

        if ($this->buffer->wantsFlushing()) {
            $records = $this->buffer->flush();

            if ($this->flushBufferAfterDelayTimer !== null) {
                $this->loop->cancelTimer($this->flushBufferAfterDelayTimer);

                $this->flushBufferAfterDelayTimer = null;
            }

            $this->ingest($records);
        } elseif ($this->buffer->isNotEmpty()) {
            $this->flushBufferAfterDelayTimer ??= $this->loop->addTimer($this->maxBufferDurationInSeconds, function (): void {
                $records = $this->buffer->flush();

                $this->flushBufferAfterDelayTimer = null;

                $this->ingest($records);
            });
        }
    }

    private function ingest(string $payload): void
    {
        if ($this->concurrentRequests >= $this->concurrentRequestLimit) {
            call_user_func($this->onIngestError, new RuntimeException("Exceeded concurrent request limit. [{$this->concurrentRequestLimit}] requests are processing"), 0.0);

            return;
        }

        // TODO determine what level is optimal here
        $payload = gzencode($payload);

        if ($payload === false) {
            call_user_func($this->onIngestError, new RuntimeException('Unable to compress payload.'), 0.0);

            return;
        }

        $this->concurrentRequests++;
        $start = microtime(true);

        $this->ingestDetails->get()->then(function (?IngestDetails $ingestDetails) use ($payload, &$start): PromiseInterface {
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
        })->then(function (ResponseInterface $response) use (&$start): void {
            /** @var array{remaining: int} */
            $content = json_decode($response->getBody()->getContents(), associative: true, flags: JSON_THROW_ON_ERROR);

            if ($content['remaining'] <= 0) {
                call_user_func($this->onExceededQuota, microtime(true) - $start);

                return;
            }

            call_user_func($this->onIngestSuccess, $response, microtime(true) - $start);
        })->catch(function (Throwable $e) use (&$start): void {
            call_user_func($this->onIngestError, $this->parseException($e), microtime(true) - $start);
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
