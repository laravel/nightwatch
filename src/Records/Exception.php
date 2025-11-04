<?php

namespace Laravel\Nightwatch\Records;

final class Exception
{
    public function __construct(
        public readonly string $class,
        public string $message,
        public readonly int|string $code,
        public readonly string $file,
        public readonly ?int $line,
        public readonly bool $handled,
    ) {
        //
    }
}
