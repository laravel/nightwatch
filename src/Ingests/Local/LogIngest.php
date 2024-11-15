<?php

namespace Laravel\Nightwatch\Ingests\Local;

use Illuminate\Log\LogManager;
use Laravel\Nightwatch\Contracts\LocalIngest;

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
        $this->log->debug('[nightwatch] Locally ingested '.$payload);
    }
}
