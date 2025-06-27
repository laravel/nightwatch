<?php

namespace Laravel\Nightwatch\Records;

use Throwable;

final class JobAttempt
{
    public function __construct(
        public string $jobId,
        public string $name,
        public string $connection,
        public string $queue,
        public ?Throwable $exception,
    ) {
        //
    }
}
