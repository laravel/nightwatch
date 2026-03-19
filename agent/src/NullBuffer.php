<?php

namespace Laravel\NightwatchAgent;

class NullBuffer
{
    public function write(Payload $payload): void
    {
        //
    }

    public function reachedThreshold(): bool
    {
        return false;
    }

    public function willExceedThresholdWith(Payload $payload): bool
    {
        return false;
    }

    /**
     * @return non-empty-string
     */
    public function pull(): string
    {
        return '{"records":[]}';
    }

    public function isNotEmpty(): bool
    {
        return false;
    }
}
