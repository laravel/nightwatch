<?php

namespace Laravel\Nightwatch;

final class RecordBuffer
{
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
        if ($input === '') {
            return;
        }

        if ($this->content === '') {
            $this->content = $input;
        } else {
            $this->content .= ",{$input}";
        }
    }

    public function wantsFlushing(): bool
    {
        return strlen($this->content) >= $this->threshold;
    }

    /**
     * @return non-empty-string
     */
    public function flush(): string
    {
        $output = '{"records":['.$this->content.']}';

        $this->content = '';

        return $output;
    }

    public function isNotEmpty(): bool
    {
        return $this->content !== '';
    }
}
