<?php

namespace Laravel\Nightwatch;

use Laravel\Nightwatch\Contracts\Clock as ClockContract;

final class Clock implements ClockContract
{
    public function __construct(private int $executionStartMicrotime)
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

    public function executionOffset(float $microtime): float
    {
        return (int) round(($microtime - $this->executionStartMicrotime) * 1000 * 1000);
    }
}
