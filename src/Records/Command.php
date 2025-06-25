<?php

namespace Laravel\Nightwatch\Records;

final class Command
{
    public function __construct(
        public readonly string $class,
        public readonly string $name,
        public string $command,
        public readonly int $exitCode,
        public readonly int $duration,
    ) {
        //
    }
}
