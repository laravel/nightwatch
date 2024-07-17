<?php

namespace Laravel\Nightwatch\Types;

/**
 * @internal
 */
final class Text
{
    public static function limit(string $value): string
    {
        if (strlen($value) > 65_535) {
            return substr($value, 0, 65_535);
        }

        return $value;
    }
}
