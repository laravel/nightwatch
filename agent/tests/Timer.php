<?php

namespace Tests;

class Timer
{
    public function __construct(
        public float|int $interval,
        public string $scheduledBy,
        public float|int $scheduledAt,
        public float|int|null $runAt = null,
        public float|int|null $canceledAt = null,
        public bool $periodic = false,
    ) {
        //
    }
}
