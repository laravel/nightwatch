<?php

namespace Laravel\NightwatchAgent;

use function explode;
use function str_contains;
use function strlen;

class Payload
{
    public string $value = '';

    public ?int $length = null;

    public bool $complete = false;

    public function append(string $chunk): void
    {
        $this->value .= $chunk;

        $this->parsePayload();

        $this->complete = $this->length === strlen($this->value);
    }

    private function parsePayload(): void
    {
        if ($this->length !== null) {
            return;
        }

        if (! str_contains($this->value, ':')) {
            return;
        }

        $bits = explode(':', $this->value, 2);
        $this->length = (int) $bits[0];
        $this->value = $bits[1];
    }
}
