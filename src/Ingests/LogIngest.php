<?php

namespace Laravel\Nightwatch\Ingests;

use Illuminate\Log\LogManager;
use Laravel\Nightwatch\Contracts\LocalIngest;

use function json_decode;
use function json_encode;

/**
 * @internal
 */
final class LogIngest implements LocalIngest
{
    public function __construct(private LogManager $log)
    {
        //
    }

    public function write(string $payload): void
    {
        $this->log->debug('Nightwatch ingest: '.json_encode(json_decode($payload), flags: JSON_PRETTY_PRINT));
    }
}
