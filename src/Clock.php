<?php

namespace Laravel\Nightwatch;

use Laravel\Nightwatch\Contracts\Clock as ClockContract;

final class Clock implements ClockContract
{
    public function microtime(): float
    {
        return microtime(true);
    }

    public function diffInMicrotime(float $start): float
    {
        return microtime(true) - $start;
    }
}
