<?php

namespace Laravel\Nightwatch;

class PeakMemoryUsage
{
    public function __invoke(): int
    {
        return memory_get_peak_usage(true);
    }
}
