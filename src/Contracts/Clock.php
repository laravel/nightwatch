<?php

namespace Laravel\Nightwatch\Contracts;

/**
 * @internal
 */
interface Clock
{
    public function nowInMicroseconds(): int;

    public function diffInMicrotime(float $start): float;

    public function executionStartInMicroseconds(): int;
}
