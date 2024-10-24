<?php

namespace Laravel\Nightwatch\Ingests;

use Illuminate\Log\LogManager;
use Laravel\Nightwatch\Contracts\Ingest;

use function json_decode;

/**
 * @internal
 */
final class LogIngest implements Ingest
{
    public function __construct(private LogManager $log)
    {
        //
    }

    public function write(string $payload): void
    {
        $this->log->info('Nightwatch ingest.', json_decode($payload, associative: true));
    }
}
