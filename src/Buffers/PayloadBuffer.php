<?php

namespace Laravel\Nightwatch\Buffers;

final class PayloadBuffer
{
    protected string $records = '';

    /**
     * @param  non-negative-int  $threshold
     */
    public function __construct(
        private int $threshold,
    ) {
        //
    }

    public function write(string $input): void
    {
        if ($input === '') {
            return;
        }

        if ($this->records === '') {
            $this->records = $input;
        } else {
            $this->records .= ",{$input}";
        }
    }

    public function wantsFlushing(): bool
    {
        return strlen($this->records) >= $this->threshold;
    }

    /**
     * @return non-empty-string
     */
    public function flush(): string
    {
        $payload = '{"records":['.$this->records.']}';

        $this->records = '';

        return $payload;
    }

    public function isNotEmpty(): bool
    {
        return $this->records !== '';
    }
}
