<?php

namespace Laravel\Nightwatch\Buffers;

use function strlen;

/**
 * @internal
 */
final class PayloadBuffer
{
    protected string $records = '';

    public function __construct(private int $threshold)
    {
        //
    }

    public function write(string $input): void
    {
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
