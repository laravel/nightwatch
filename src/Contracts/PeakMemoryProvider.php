<?php

namespace Laravel\Nightwatch\Contracts;

interface PeakMemoryProvider
{
    public function kilobytes(): int;
}
