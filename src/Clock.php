<?php

namespace Laravel\Nightwatch;

use Laravel\Nightwatch\Contracts\Clock as ClockContract;

/**
 * @internal
 */
final class Clock implements ClockContract
{
    public function __construct(private int $executionStartInMicrotime)
    {
        //
    }

    public function microtime(): float
    {
        return microtime(true);
    }

    public function diffInMicrotime(float $start): float
    {
        return microtime(true) - $start;
    }

    public function executionStartInMicrotime(): float
    {
        return $this->executionStartInMicrotime;
    }
}
