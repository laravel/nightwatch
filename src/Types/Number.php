<?php

namespace Laravel\Nightwatch\Types;

use function max;
use function min;

final class Number
{
    public function uInt32(int $value): int
    {
        return min(65_535, max(0, $value));
    }
}
