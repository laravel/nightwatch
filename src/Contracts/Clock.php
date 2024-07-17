<?php

namespace Laravel\Nightwatch\Contracts;

/**
 * @internal
 */
interface Clock
{
    public function microtime(): float;

    public function diffInMicrotime(float $start): float;

    public function executionOffset(float $nowMicrotime): int;

    public function executionStartMicrotime(): float;
}
