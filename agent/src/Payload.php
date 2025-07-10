<?php

namespace Laravel\NightwatchAgent;

use function count;
use function explode;
use function strlen;

class Payload
{
    public const EXPECTED_PAYLOAD_VERSION = 'v1';

    public string $value = '';

    public string $version = '';

    public string $tokenHash = '';

    public ?int $length = null;

    public bool $complete = false;

    public function append(string $chunk): void
    {
        $this->value .= $chunk;

        $this->parsePayload();

        $this->complete = $this->length === (strlen($this->version) + 1 + (strlen($this->tokenHash)) + 1 + strlen($this->value));
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
        $this->version = $bits[1];
        $this->tokenHash = $bits[2];
        $this->value = $bits[3];
    }

    public function versionIsValid(): bool
    {
        return $this->version === self::EXPECTED_PAYLOAD_VERSION;
    }
}
