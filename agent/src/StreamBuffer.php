<?php

namespace Laravel\NightwatchAgent;

use function strlen;
use function substr;

class StreamBuffer
{
    private string $buffer = '';

    public function __construct(
        private int $threshold,
    ) {
        //
    }

    public function write(string $payload): void
    {
        $input = substr($payload, 1, -1);

        if ($this->buffer === '') {
            $this->buffer = $input;
        } else {
            $this->buffer .= ",{$input}";
        }
    }

    public function reachedThreshold(): bool
    {
        return strlen($this->buffer) >= $this->threshold;
    }

    public function willExceedThresholdWith(string $payload): bool
    {
        // -2 to account for the removal of the `[` and `]` characters when
        // appending to the stream.
        return (strlen($this->buffer) + (strlen($payload) - 2)) > $this->threshold;
    }

    /**
     * @return non-empty-string
     */
    public function pull(): string
    {
        $payload = '{"records":['.$this->buffer.']}';

        $this->buffer = '';

        return $payload;
    }

    public function isNotEmpty(): bool
    {
        return $this->buffer !== '';
    }

    public function flush(): void
    {
        $this->buffer = '';
    }
}
