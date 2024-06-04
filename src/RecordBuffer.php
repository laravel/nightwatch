<?php

namespace Laravel\Package;

final class RecordBuffer
{
    /**
     * @var non-negative-int
     */
    private int $length = 0;

    protected string $content = '';

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
        $length = strlen($input);

        if ($length === 0) {
            return;
        }

        $this->length += $length;
        $this->content .= $input;
    }

    public function wantsFlushing(): bool
    {
        return $this->length >= $this->threshold;
    }

    /**
     * @return non-empty-string
     */
    public function flush(): string
    {
        $output = $this->toString();

        $this->content = '';
        $this->length = 0;

        return $output;
    }

    /**
     * @return non-empty-string
     */
    public function toString(): string
    {
        return '{"records":['.$this->content.']}';
    }
}
