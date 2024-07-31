<?php

namespace Laravel\Nightwatch\Types;

class Str
{
    public static function tinyText(string $value)
    {
        return static::restrict($value, 255);
    }

    public static function text(string $value): string
    {
        return static::restrict($value, 65_535);
    }

    public static function mediumText(string $value): string
    {
        return static::restrict($value, 16_777_215);
    }

    public static function restrict(string $string, int $length)
    {
        if (strlen($string) > $length) {
            return substr($string, 0, $length);
        }

        return $string;
    }
}
