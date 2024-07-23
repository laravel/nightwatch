<?php

namespace Laravel\Nightwatch;

use Laravel\Nightwatch\Contracts\Clock as ClockContract;

/**
 * @internal
 */
final class Clock implements ClockContract
{
    private ?int $executionStartInMicroseconds = null;

    public function __construct(private int $executionStartMicrotime)
    {
        //
    }

    public function nowInMicroseconds(): int
    {
        return intval(microtime(true) * 1_000_000);
    }

    public function diffInMicrotime(float $start): float
    {
        return microtime(true) - $start;
    }

    public function executionStartInMicroseconds(): int
    {
        return $this->executionStartInMicroseconds ??= intval($this->executionStartMicrotime * 1_000_000);
    }
}
