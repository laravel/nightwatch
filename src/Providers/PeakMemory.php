<?php

namespace Laravel\Nightwatch\Providers;

use Laravel\Nightwatch\Contracts\PeakMemoryProvider;

final class PeakMemory implements PeakMemoryProvider
{
    public function kilobytes(): int
    {
        return (int) round((memory_get_peak_usage(true) / 1000));
    }
}
