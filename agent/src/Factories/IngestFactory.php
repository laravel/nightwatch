<?php

namespace Laravel\NightwatchAgent\Factories;

use Closure;
use Laravel\NightwatchAgent\Ingest;
use Laravel\NightwatchAgent\IngestDetailsRepository;
use Laravel\NightwatchAgent\PackageVersionRepository;
use Laravel\NightwatchAgent\StreamBuffer;
use Psr\Http\Message\ResponseInterface;
use React\Http\Browser;
use React\Socket\Connector;
use Throwable;

class IngestFactory
{
    /**
     * @param  (Closure(ResponseInterface $response, float $duration): mixed)  $onIngestSuccess
     * @param  (Closure(Throwable $e, float $duration): mixed)  $onIngestError
     */
    public function __invoke(
        IngestDetailsRepository $ingestDetails,
        float $connectionTimeout,
        float $timeout,
        bool $debug,
        int $threshold,
        int $concurrentRequestLimit,
        int $maxBufferDurationInSeconds,
        PackageVersionRepository $packageVersion,
        Closure $onIngestSuccess,
        Closure $onIngestError,
    ): Ingest {
        $connector = new Connector(['timeout' => $connectionTimeout]);

        $browser = (new Browser($connector))
            ->withTimeout($timeout)
            ->withHeader('content-type', 'application/octet-stream')
            ->withHeader('content-encoding', 'gzip');

        if ($debug) {
            $browser = $browser->withHeader('nightwatch-debug', '1');
        }

        $buffer = new StreamBuffer($threshold);

        return new Ingest(
            browser: $browser,
            ingestDetails: $ingestDetails,
            buffer: $buffer,
            concurrentRequestLimit: $concurrentRequestLimit,
            maxBufferDurationInSeconds: $debug ? 1 : $maxBufferDurationInSeconds,
            packageVersion: $packageVersion,
            onIngestSuccess: $onIngestSuccess,
            onIngestError: $onIngestError,
        );
    }
}
