<?php

namespace Laravel\Nightwatch\Types;

use Illuminate\Support\Str as SupportStr;

use function strlen;
use function substr;

/**
 * @mixin \Illuminate\Support\Str
 */
final class Str
{
    public static function tinyText(string $value): string
    {
        return self::restrict($value, 255);
    }

    public static function text(string $value): string
    {
        return self::restrict($value, 65_535);
    }

    public static function mediumText(string $value): string
    {
        return self::restrict($value, 16_777_215);
    }

    public static function restrict(string $string, int $length): string
    {
        if (strlen($string) > $length) {
            return substr($string, 0, $length);
        }

        return $string;
    }

    /**
     * @param  list<mixed>  $arguments
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        return SupportStr::{$name}(...$arguments);
    }
}
