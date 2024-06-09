<?php

namespace Laravel\Nightwatch\Providers;

use Laravel\Nightwatch\Contracts\PeakMemoryProvider;

final class PeakMemory implements PeakMemoryProvider
{
    public function kilobytes(): int
    {
        // TODO: do we need to reset this in Octane, Queue worker, or other
        // long running processes?
        return (int) (memory_get_peak_usage(true) / 1000);
    }
}
