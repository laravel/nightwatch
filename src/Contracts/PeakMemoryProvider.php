<?php

namespace Laravel\Nightwatch\Contracts;

/**
 * @internal
 */
interface PeakMemoryProvider
{
    public function kilobytes(): int;
}
