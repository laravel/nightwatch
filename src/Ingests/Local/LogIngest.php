<?php

namespace Laravel\Nightwatch\Ingests\Local;

use Laravel\Nightwatch\Contracts\LocalIngest;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
final class LogIngest implements LocalIngest
{
    public function __construct(private LoggerInterface $log)
    {
        //
    }

    public function write(string $payload): void
    {
        $this->log->debug('[nightwatch] Locally ingested '.$payload);
    }
}
