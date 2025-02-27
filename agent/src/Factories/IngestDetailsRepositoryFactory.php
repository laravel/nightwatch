<?php

namespace Laravel\NightwatchAgent\Factories;

use Closure;
use Laravel\NightwatchAgent\IngestDetails;
use Laravel\NightwatchAgent\IngestDetailsRepository;
use Laravel\NightwatchAgent\PackageVersionRepository;
use React\Http\Browser;
use React\Socket\Connector;
use Throwable;

use function rtrim;

class IngestDetailsRepositoryFactory
{
    /**
     * @param  (Closure(IngestDetails $ingestDetails, float $duration): mixed)  $onAuthenticationSuccess
     * @param  (Closure(Throwable $e, float $duration): mixed)  $onAuthenticationError
     */
    public function __invoke(
        string $baseUrl,
        string $refreshToken,
        float $connectionTimeout,
        float $timeout,
        int $preemptivelyRefreshInSeconds,
        int $minRefreshDurationInSeconds,
        PackageVersionRepository $packageVersion,
        Closure $onAuthenticationSuccess,
        Closure $onAuthenticationError,
    ): IngestDetailsRepository {
        $connector = new Connector(['timeout' => $connectionTimeout]);

        $browser = (new Browser($connector))
            ->withTimeout($timeout)
            ->withHeader('authorization', "Bearer {$refreshToken}")
            ->withHeader('content-type', 'application/json')
            ->withBase(rtrim($baseUrl, '/').'/api/agent-auth');

        return new IngestDetailsRepository(
            browser: $browser,
            preemptivelyRefreshInSeconds: $preemptivelyRefreshInSeconds,
            minRefreshDurationInSeconds: $minRefreshDurationInSeconds,
            packageVersion: $packageVersion,
            onAuthenticationSuccess: $onAuthenticationSuccess,
            onAuthenticationError: $onAuthenticationError,
        );
    }
}
