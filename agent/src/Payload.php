<?php

namespace Laravel\NightwatchAgent;

use function count;
use function explode;
use function strlen;

class Payload
{
    public string $value = '';

    public string $payloadVersion = '';

    public string $tokenHash = '';

    public ?int $length = null;

    public bool $complete = false;

    public function append(string $chunk): void
    {
        $this->value .= $chunk;

        $this->parsePayload();

        $this->complete = $this->length === (strlen($this->payloadVersion) + 1 + (strlen($this->tokenHash)) + 1 + strlen($this->value));
    }

    private function parsePayload(): void
    {
        if ($this->length !== null) {
            return;
        }

        $bits = explode(':', $this->value, 4);

        if (count($bits) !== 4) {
            return;
        }

        $this->length = (int) $bits[0];
        $this->payloadVersion = $bits[1];
        $this->tokenHash = $bits[2];
        $this->value = $bits[3];
    }
}
