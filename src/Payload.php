<?php

namespace Laravel\Nightwatch;

use RuntimeException;

use function in_array;
use function strlen;
use function hash;

/**
 * @internal
 */
final class Payload
{
    /**
     * This value is automatically updated in CI.
     *
     * Do not modify or re-locate this constant.
     */
    public const PAYLOAD_VERSION = 'v1';

    //    public const NIGHWATCH_TOKEN_HASH = hash('xxh128', $this->nightwatchConfig['token']) ?? null;

    private bool $pulled = false;

    /**
     * @param  'TEXT'|'JSON'  $type
     */
    public function __construct(
        private string $type,
        private string $payload,
    ) {
        //
    }

    public static function text(string $payload): self
    {
        return new self('TEXT', $payload);
    }

    public static function json(string $payload): self
    {
        return new self('JSON', $payload);
    }

    public function pull(): string
    {
        if ($this->pulled) {
            throw new RuntimeException('Payload has already been read');
        }

        $this->pulled = true;
        $payload = $this->payload;

        $this->payload = '';

        $length = strlen(self::PAYLOAD_VERSION) + 1 + strlen($payload);

        return $length.':'.self::PAYLOAD_VERSION.':'.$payload;
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
