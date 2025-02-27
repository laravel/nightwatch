<?php

namespace Laravel\NightwatchAgent;

use Closure;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Loop;
use React\Http\Browser;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use RuntimeException;
use Throwable;

use function call_user_func;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function max;
use function microtime;
use function React\Promise\resolve;

class IngestDetailsRepository
{
    /**
     * @var PromiseInterface<IngestDetails|null>|null
     */
    private ?PromiseInterface $ingestDetails = null;

    /**
     * @param  (Closure(IngestDetails $ingestDetails, float $duration): mixed)  $onAuthenticationSuccess
     * @param  (Closure(Throwable $e, float $duration): mixed)  $onAuthenticationError
     */
    public function __construct(
        private Browser $browser,
        private int $preemptivelyRefreshInSeconds,
        private int $minRefreshDurationInSeconds,
        private Closure $onAuthenticationSuccess,
        private Closure $onAuthenticationError,
    ) {
        //
    }

    public function hydrate(): void
    {
        $this->get();
    }

    /**
     * @return PromiseInterface<IngestDetails|null>
     */
    public function get(): PromiseInterface
    {
        return $this->ingestDetails ??= $this->refresh();
    }

    /**
     * @return PromiseInterface<IngestDetails|null>
     */
    private function refresh(): PromiseInterface
    {
        $start = microtime(true);

        return $this->browser->post('')->then(function (ResponseInterface $response) use ($start): IngestDetails {
            $duration = microtime(true) - $start;

            $data = json_decode($response->getBody()->getContents(), associative: true, flags: JSON_THROW_ON_ERROR);

            if (
                ! is_array($data) ||
                ! is_string($data['token'] ?? null) ||
                ! is_int($data['expires_in'] ?? null) ||
                ! is_string($data['ingest_url'] ?? null)
            ) {
                throw new RuntimeException("Invalid authentication response [{$response->getBody()->getContents()}].");
            }

            $ingestDetails = new IngestDetails($data['token'], $data['expires_in'], $data['ingest_url']);

            $this->scheduleRefreshIn(seconds: $ingestDetails->expiresIn - $this->preemptivelyRefreshInSeconds);

            call_user_func($this->onAuthenticationSuccess, $ingestDetails, $duration);

            return $ingestDetails;
        }, function (Throwable $e) use ($start): null {
            $duration = microtime(true) - $start;

            // On first failure, the old key will still work for the next ~60 seconds.
            // Will comment this out until we have a solid retry mechanism in place.
            // We won't want to keep this in place all the time as the agent should
            // stop sending data if the key has expired. We could probably capture
            // the current timestamp on refresh with the expires_in added. Each
            // time the key is retrieved we check if it has expired and return
            // null if we believe it has.
            // $this->ingestDetails = new Promise(fn () => null);
            // TODO schedule retry after failure

            call_user_func($this->onAuthenticationError, $e, $duration);

            return null;
        })->catch(function (Throwable $e): null {
            // TODO schedule retry
            call_user_func($this->onAuthenticationError, $e, 0.0);

            return null;
        });
    }

    private function scheduleRefreshIn(int $seconds): void
    {
        $seconds = max($this->minRefreshDurationInSeconds, $seconds);

        Loop::addTimer($seconds, function (): void {
            // TODO must not throw an exception
            $this->refresh()->then(function (?IngestDetails $ingestDetails): void {
                $this->ingestDetails = resolve($ingestDetails);
            });
        });
    }
}
