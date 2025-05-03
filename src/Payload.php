<?php

namespace Laravel\Nightwatch;

use function strlen;

/**
 * @internal
 */
final class Payload
{
    // TODO
    public const SIGNATURE = '57EBE5A';

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

    public function write(string $payload): void
    {
        $this->payload .= $payload;
    }

    public function pull(): string
    {
        $payload = $this->payload;

        $this->payload = '';

        $length = strlen(self::SIGNATURE) + 1 + strlen($payload);

        return $length.':'.self::SIGNATURE.':'.$payload;
    }

    public function sourcePayload(): string
    {
        return $this->payload;
    }

    public function isEmpty(): bool
    {
        return match ($this->type) {
            'JSON' => $this->payload === '[]',
            'TEXT' => $this->payload === '',
        };
    }
}
