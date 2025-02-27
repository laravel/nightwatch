<?php

namespace Laravel\NightwatchAgent;

use Closure;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;
use React\Http\Browser;
use React\Promise\PromiseInterface;
use RuntimeException;
use Throwable;

use function call_user_func;
use function gzencode;
use function microtime;

class Ingest
{
    private int $concurrentRequests = 0;

    private ?TimerInterface $flushBufferAfterDelayTimer = null;

    /**
     * @param  (Closure(ResponseInterface $response, float $duration): mixed)  $onIngestSuccess
     * @param  (Closure(Throwable $e, float $duration): mixed)  $onIngestError
     */
    public function __construct(
        private Browser $browser,
        private IngestDetailsRepository $ingestDetails,
        private StreamBuffer $buffer,
        private int $concurrentRequestLimit,
        private int $maxBufferDurationInSeconds,
        private PackageVersionRepository $packageVersion,
        private Closure $onIngestSuccess,
        private Closure $onIngestError,
    ) {
        //
    }

    public function write(string $payload): void
    {
        $this->buffer->write($payload);

        if ($this->buffer->wantsFlushing()) {
            $records = $this->buffer->flush();

            if ($this->flushBufferAfterDelayTimer !== null) {
                Loop::cancelTimer($this->flushBufferAfterDelayTimer);

                $this->flushBufferAfterDelayTimer = null;
            }

            $this->ingest($records);
        } elseif ($this->buffer->isNotEmpty()) {
            $this->flushBufferAfterDelayTimer ??= Loop::addTimer($this->maxBufferDurationInSeconds, function (): void {
                $records = $this->buffer->flush();

                $this->flushBufferAfterDelayTimer = null;

                $this->ingest($records);
            });
        }
    }

    private function ingest(string $payload): void
    {
        if ($this->concurrentRequests >= $this->concurrentRequestLimit) {
            call_user_func($this->onIngestError, new RuntimeException('Exceeded concurrent request limit.'), 0.0);

            return;
        }

        // TODO determine what level is optimal here
        $payload = gzencode($payload);

        if ($payload === false) {
            call_user_func($this->onIngestError, new RuntimeException('Unable to compress payload.'), 0.0);

            return;
        }

        $this->concurrentRequests++;

        $this->ingestDetails->get()->then(function (?IngestDetails $ingestDetails) use ($payload): PromiseInterface {
            if ($ingestDetails === null) {
                throw new RuntimeException('Unable to ingest payload. No authentication details found.');
            }

            $start = microtime(true);

            return $this->browser->post(
                url: $ingestDetails->ingestUrl,
                headers: [
                    'authorization' => "Bearer {$ingestDetails->token}",
                    'user-agent' => 'NightwatchAgent/'.$this->packageVersion->get(),
                ],
                body: $payload,
            )->then(
                function (ResponseInterface $response) use ($start): void {
                    call_user_func($this->onIngestSuccess, $response, microtime(true) - $start);
                },
                function (Throwable $e) use ($start): void {
                    call_user_func($this->onIngestError, $e, microtime(true) - $start);
                }
            );
        })->catch(function (Throwable $e): void {
            call_user_func($this->onIngestError, $e, 0.0);
        })->finally(function (): void {
            $this->concurrentRequests--;
        });
    }
}
