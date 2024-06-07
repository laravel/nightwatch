<?php

namespace Laravel\Nightwatch\Contracts;

interface PeakMemoryProvider
{
    public function inKilobytes(): int;
}
