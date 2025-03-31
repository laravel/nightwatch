<?php

namespace Laravel\NightwatchAgent;

use Closure;
use Laravel\NightwatchAgent\Contracts\Browser;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Loop;
use React\Http\Message\ResponseException;
use React\Promise\PromiseInterface;
use RuntimeException;
use Throwable;

use function array_fill;
use function call_user_func;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function microtime;
use function React\Promise\resolve;
use function strlen;
use function substr;

class IngestDetailsRepository
{
    /**
     * @var PromiseInterface<IngestDetails|null>|null
     */
    private ?PromiseInterface $ingestDetails = null;

    private bool $hasAuthenticated = false;

    private int $consecutiveFailures = 0;

    /**
     * @var list<int|float>|null
     */
    private ?array $quickRetryStrategyDurationsCache = null;

    /**
     * @param  Browser  $browser
     * @param  (Closure(IngestDetails $ingestDetails, float $duration): mixed)  $onAuthenticationSuccess
     * @param  (Closure(Throwable $e, float $duration): mixed)  $onAuthenticationError
     */
    public function __construct(
        private $browser,
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
        $duration = null;

        return $this->browser->post('/api/agent-auth', headers: [], body: '')
            ->then(function (ResponseInterface $response) use ($start, &$duration): IngestDetails {
                $duration = microtime(true) - $start;

                $ingestDetails = $this->parseResponse($response);

                $this->scheduleRefreshIn($ingestDetails->refreshIn);

                call_user_func($this->onAuthenticationSuccess, $ingestDetails, $duration);

                $this->hasAuthenticated = true;
                $this->consecutiveFailures = 0;

                return $ingestDetails;
            })->catch(function (Throwable $e) use ($start, &$duration): null {
                $this->consecutiveFailures++;

                // TODO if the current token has expired we should `null` it.
                $duration ??= microtime(true) - $start;

                [$e, $interval] = $this->parseException($e);

                $this->scheduleRefreshIn($interval);

                call_user_func($this->onAuthenticationError, $e, $duration);

                return null;
            });
    }

    private function scheduleRefreshIn(int|float $seconds): void
    {
        Loop::addTimer($seconds, function (): void {
            $this->refresh()->then(function (?IngestDetails $ingestDetails): void {
                if ($ingestDetails) {
                    $this->ingestDetails = resolve($ingestDetails);
                }
            });
        });
    }

    private function parseResponse(ResponseInterface $response): IngestDetails
    {
        $body = $response->getBody()->getContents();

        $data = json_decode($body, associative: true, flags: JSON_THROW_ON_ERROR);

        if (
            ! is_array($data) ||
            ! is_string($data['token'] ?? null) ||
            ! is_int($data['expires_in'] ?? null) ||
            ! is_int($data['refresh_in'] ?? null) ||
            ! is_string($data['ingest_url'] ?? null)
        ) {
            throw new RuntimeException("Invalid authentication response [{$body}].");
        }

        return new IngestDetails(
            token: $data['token'],
            expiresIn: $data['expires_in'],
            ingestUrl: $data['ingest_url'],
            refreshIn: $data['refresh_in'],
        );
    }

    /**
     * @return array{0: Throwable, 1: int|float}
     */
    private function parseException(Throwable $e): array
    {
        return $e instanceof ResponseException
            ? $this->parseResponseException($e)
            : $this->parseNonResponseException($e);
    }

    /**
     * @return array{0: Throwable, 1: int|float}
     */
    private function parseResponseException(ResponseException $e): array
    {
        $status = $e->getResponse()->getStatusCode();
        $body = $e->getResponse()->getBody()->getContents();

        if (strlen($body) > 255) {
            $body = substr($body, 0, 250).'[...]';
        }

        $e = new RuntimeException("{$status} [{$body}]");

        if ($status === 401) {
            return [$e, 3_600];
        }

        return $this->hasAuthenticated
            ? [$e, $this->slowRetryStrategy()]
            : [$e, $this->quickRetryStrategy()];
    }

    /**
     * @return array{0: Throwable, 1: int|float}
     */
    private function parseNonResponseException(Throwable $e): array
    {
        return $this->hasAuthenticated
            ? [$e, $this->slowRetryStrategy()]
            : [$e, $this->quickRetryStrategy()];
    }

    private function quickRetryStrategy(): int|float
    {
        $strategy = $this->quickRetryStrategyDurationsCache ??= [2.5, 5, 10, 15, 30, 60, 120, 240, ...array_fill(0, 12, 300)];

        return $strategy[$this->consecutiveFailures - 1] ?? 3_600;
    }

    private function slowRetryStrategy(): int
    {
        return $this->consecutiveFailures < 13
            ? 300
            : 3_600;
    }
}
