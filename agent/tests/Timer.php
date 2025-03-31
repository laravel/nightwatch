<?php

namespace Tests;

class Timer
{
    public function __construct(
        public float|int $interval,
        public float|int $runAt,
        public string $scheduledBy,
    ) {
        //
    }
}
