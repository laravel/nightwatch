<?php

namespace Laravel\Nightwatch\Types;

final class MediumText
{
    public static function limit(string $value): string
    {
        if (strlen($value) > 16_777_215) {
            return substr($value, 0, 16_777_215);
        }

        return $value;
    }
}
