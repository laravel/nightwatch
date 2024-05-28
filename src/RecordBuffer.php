<?php

namespace Laravel\Package;

class RecordBuffer
{
    /** @var non-negative-int */
    private int $length = 0;

    /** @var  list<non-empty-string> */
    protected array $content = [];

    /** @param non-negative-int $threshold */
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
        $this->content[] = $input;
    }

    public function wantsFlushing(): bool
    {
        return $this->length >= $this->threshold;
    }

    public function flush(): string
    {
        return '{"records":['.implode(',', $this->content).']}';
    }
}

