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
