<?php

namespace Tests\Unit;

use Laravel\Nightwatch\Payload;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;
use Throwable;

use function json_encode;

class PayloadTest extends TestCase
{
    #[DataProvider('jsonPayloads')]
    public function test_it_can_determine_if_a_json_payload_is_empty(mixed $value, bool $empty): void
    {
        $tokenHash = self::tokenHash();
        $payload = Payload::json(json_encode($value, flags: JSON_THROW_ON_ERROR), $tokenHash);

        $this->assertSame($empty, $payload->isEmpty());
    }

    public static function jsonPayloads(): iterable
    {
        yield [null, true];
        yield [true, false];
        yield [false, false];
        yield [0, false];
        yield [1, false];
        yield [-1, false];
        yield ['', true];
        yield [' ', false];
        yield ['a', false];
        yield [[], true];
        yield [[1], false];
        yield [(object) [], true];
        yield [(object) ['a' => 1], false];
    }

    #[DataProvider('textPayloads')]
    public function test_it_can_determine_if_a_text_payload_is_empty(string $value, bool $empty): void
    {
        $payload = Payload::text($value, 'tokenHash');

        $this->assertSame($empty, $payload->isEmpty());
    }

    public static function textPayloads(): iterable
    {
        yield ['', true];
        yield [' ', false];
        yield ['a', false];
    }

    public function test_it_can_pull_the_bencoded_signed_value(): void
    {
        $tokenHash = self::tokenHash();
        $payload = Payload::text('abc123', $tokenHash);
        $encoded = $payload->pull();

        $this->assertSame('17:'.Payload::PAYLOAD_VERSION.':'.$tokenHash.':abc123', $encoded);
    }

    public function test_it_can_only_pull_the_payload_once(): void
    {
        $tokenHash = self::tokenHash();
        $payload = Payload::text('abc123', $tokenHash);
        $payload->pull();

        try {
            $payload->pull();
            throw new RuntimeException;
        } catch (Throwable $e) {
            $this->assertSame('Payload has already been read', $e->getMessage());
        }
    }

    public function test_it_frees_memory_after_pulling_the_payload(): void
    {
        $payload = Payload::text('abc123', 'tokenHash');

        $this->assertSame('abc123', $payload->rawPayload());

        $payload->pull();
        $this->assertSame('', $payload->rawPayload());
    }

    public function test_it_has_up_to_date_payload_version(): void
    {
        $payloadVersion = 'v1';
        $this->assertSame($payloadVersion, Payload::PAYLOAD_VERSION, 'Payload version has changed! this indicates that a new major version must be tagged');
    }
}
