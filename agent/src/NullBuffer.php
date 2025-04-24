<?php

namespace Laravel\NightwatchAgent;

class NullBuffer
{
    public function write(string $payload): void
    {
        //
    }

    public function reachedThreshold(): bool
    {
        return false;
    }

    /**
     * @return non-empty-string
     */
    public function read(): string
    {
        return '{"records":[]}';
    }

    public function isNotEmpty(): bool
    {
        return false;
    }
}
