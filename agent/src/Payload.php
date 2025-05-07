<?php

namespace Laravel\NightwatchAgent;

use function count;
use function explode;
use function strlen;

class Payload
{
    public string $value = '';

    public string $signature = '';

    public ?int $length = null;

    public bool $complete = false;

    public function append(string $chunk): void
    {
        $this->value .= $chunk;

        $this->parsePayload();

        $this->complete = $this->length === (strlen($this->signature) + 1 + strlen($this->value));
    }

    private function parsePayload(): void
    {
        if ($this->length !== null) {
            return;
        }

        $bits = explode(':', $this->value, 3);

        if (count($bits) !== 3) {
            return;
        }

        $this->length = (int) $bits[0];
        $this->signature = $bits[1];
        $this->value = $bits[2];
    }
}
