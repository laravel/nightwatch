<?php

namespace Laravel\Nightwatch;

use Laravel\Nightwatch\Contracts\PeakMemoryProvider;

final class PeakMemory implements PeakMemoryProvider
{
    public function inKilobytes(): int
    {
        return (int) (memory_get_peak_usage(true) / 1000);
    }
}
