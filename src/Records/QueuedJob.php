<?php

namespace Laravel\Nightwatch\Records;

final class QueuedJob
{
    public function __construct(
        public readonly string $jobId,
        public readonly string $name,
        public readonly string $connection,
        public readonly string $queue,
        public readonly int $duration,
    ) {
        //
    }
}
