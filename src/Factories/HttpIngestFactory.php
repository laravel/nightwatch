<?php

namespace Laravel\Nightwatch\Factories;

use Illuminate\Contracts\Foundation\Application;
use Laravel\Nightwatch\IngestDetailsRepository;
use Laravel\Nightwatch\Ingests\Remote\HttpClient;
use Laravel\Nightwatch\Ingests\Remote\HttpIngest;
use React\Http\Browser;
use React\Socket\Connector;

/**
 * @internal
 */
final class HttpIngestFactory
{
    /**
     * @param  array{
     *      enabled?: bool,
     *      token?: string,
     *      deployment?: string,
     *      server?: string,
     *      local_ingest?: string,
     *      remote_ingest?: string,
     *      buffer_threshold?: int,
     *      error_log_channel?: string,
     *      ingests: array{
     *          socket?: array{ uri?: string, connection_timeout?: float, timeout?: float },
     *          http?: array{ connection_timeout?: float, timeout?: float },
     *          log?: array{ channel?: string },
     *      }
     * }  $config
     */
    public function __construct(
        private array $config,
        private IngestDetailsRepository $ingestDetails,
        private bool $debug,
    ) {
        //
    }

    public function __invoke(Application $app): HttpIngest
    {
        $connector = new Connector(['timeout' => $this->config['ingests']['http']['connection_timeout'] ?? 1.0]);

        $browser = (new Browser($connector))
            ->withTimeout($this->config['ingests']['http']['timeout'] ?? 3.0)
            ->withHeader('user-agent', 'NightwatchAgent/1')
            ->withHeader('content-type', 'application/octet-stream')
            ->withHeader('content-encoding', 'gzip');

        if ($this->debug) {
            $browser = $browser->withHeader('nightwatch-debug', '1');
        }

        $client = new HttpClient($browser, $this->ingestDetails);

        return new HttpIngest($client, 2);
    }
}
