<?php

namespace Laravel\Nightwatch\Contracts;

interface Clock
{
    public function microtime(): float;

    public function diffInMicrotime(float $start): float;

    public function executionOffset(float $nowMicrotime): float;
}
