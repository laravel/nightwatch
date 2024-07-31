<?php

namespace Laravel\Nightwatch\Types;

final class Number
{
    public function uInt32(int $value): int
    {
        return min(65_535, max(0, $value));
    }
}
