<?php

namespace Laravel\NightwatchAgent;

class NullBuffer
{
    public function write(string $input): void
    {
        //
    }

    public function wantsFlushing(): bool
    {
        return false;
    }

    /**
     * @return non-empty-string
     */
    public function flush(): string
    {
        return '{"records":[]}';
    }

    public function isNotEmpty(): bool
    {
        return false;
    }
}
