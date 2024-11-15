<?php

namespace Laravel\Nightwatch\Providers;

use Closure;
use function memory_get_peak_usage;

/**
 * @internal
 */
final class PeakMemory
{
    /**
     * @var (Closure(): int)
     */
    public Closure $peakMemoryResolver;

    public function __construct()
    {
        $this->peakMemoryResolver = static fn () => memory_get_peak_usage(true);
    }

    public function bytes(): int
    {
        return call_user_func($this->peakMemoryResolver);
    }
}
