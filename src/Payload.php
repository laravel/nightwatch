<?php

namespace Laravel\Nightwatch;

use RuntimeException;

use function in_array;
use function json_encode;
use function strlen;

/**
 * @internal
 */
final class Payload
{
    public const PAYLOAD_VERSION = 'v1';

    private bool $pulled = false;

    /**
     * @param  'TEXT'|'JSON'  $type
     */
    public function __construct(
        private string $type,
        private string $payload,
        private string $tokenHash,
    ) {
        //
    }

    public static function text(string $payload, string $tokenHash): self
    {
        return new self('TEXT', $payload, $tokenHash);
    }

    /**
     * @param  list<array<string, mixed>>  $payload
     */
    public static function json(array $payload, string $tokenHash): self
    {
        return new self(
            'JSON',
            json_encode($payload, flags: JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE),
            $tokenHash
        );
    }

    public function pull(): string
    {
        if ($this->pulled) {
            throw new RuntimeException('Payload has already been read');
        }

        $this->pulled = true;
        $payload = $this->payload;

        $this->payload = '';

        $length = strlen(self::PAYLOAD_VERSION) + 1 + strlen($this->tokenHash) + 1 + strlen($payload);

        return $length.':'.self::PAYLOAD_VERSION.':'.$this->tokenHash.':'.$payload;
    }

    public function rawPayload(): string
    {
        return $this->payload;
    }

    public function isEmpty(): bool
    {
        return match ($this->type) {
            'JSON' => in_array($this->payload, ['[]', '{}', '""', 'null'], true),
            'TEXT' => $this->payload === '',
        };
    }
}
