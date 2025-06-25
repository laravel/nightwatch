<?php

namespace Laravel\Nightwatch\Records;

final class JobAttempt
{
    /**
     * @param  'processed'|'released'|'failed'  $status
     */
    public function __construct(
        public readonly string $jobId,
        public readonly string $attemptId,
        public readonly int $attempt,
        public readonly string $name,
        public readonly string $connection,
        public readonly string $queue,
        public readonly string $status,
        public readonly int $duration,
    ) {
        //
    }
}
