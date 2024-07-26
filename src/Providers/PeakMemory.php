<?php

namespace Laravel\Nightwatch\Providers;

use Laravel\Nightwatch\Contracts\PeakMemoryProvider;

/**
 * @internal
 */
final class PeakMemory implements PeakMemoryProvider
{
    public function bytes(): int
    {
        return memory_get_peak_usage(true);
    }
}
