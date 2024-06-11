<?php

namespace Laravel\Nightwatch;

final class TinyText
{
    public static function limit(string $value): string
    {
        if (strlen($value) > 255) {
            return substr($value, 0, 255);
        }

        return $value;
    }
}
