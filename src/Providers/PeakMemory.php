<?php

namespace Laravel\Nightwatch\Providers;

use Laravel\Nightwatch\Contracts\PeakMemoryProvider;

use function memory_get_peak_usage;

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
