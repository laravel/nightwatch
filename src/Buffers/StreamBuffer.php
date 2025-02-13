<?php

namespace Laravel\Nightwatch\Buffers;

use function strlen;
use function substr;

/**
 * @internal
 */
final class StreamBuffer
{
    private string $buffer = '';

    public function __construct(
        private int $threshold = 8_000_000,
    ) {
        //
    }

    public function write(string $input): void
    {
        $input = substr(substr($input, 1), 0, -1);

        if ($this->buffer === '') {
            $this->buffer = $input;
        } else {
            $this->buffer .= ",{$input}";
        }
    }

    public function wantsFlushing(): bool
    {
        return strlen($this->buffer) >= $this->threshold;
    }

    /**
     * @return non-empty-string
     */
    public function flush(): string
    {
        $payload = '{"records":['.$this->buffer.']}';

        $this->buffer = '';

        return $payload;
    }

    public function isNotEmpty(): bool
    {
        return $this->buffer !== '';
    }
}
