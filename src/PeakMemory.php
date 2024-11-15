<?php

namespace Laravel\Nightwatch;

use Closure;

use function call_user_func;
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
