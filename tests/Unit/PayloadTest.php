<?php

namespace Tests\Unit;

use Laravel\Nightwatch\Payload;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;
use Throwable;

use function file_get_contents;
use function json_encode;
use function substr;

class PayloadTest extends TestCase
{
    #[DataProvider('jsonPayloads')]
    public function test_it_can_determine_if_a_jso_n_payload_is_empty(mixed $value, bool $empty): void
    {
        $payload = Payload::json(json_encode($value, flags: JSON_THROW_ON_ERROR));

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
        $payload = Payload::text($value);

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
        $payload = Payload::text('abc123');
        $encoded = $payload->pull();

        $this->assertSame('14:'.Payload::SIGNATURE.':abc123', $encoded);
    }

    public function test_it_can_only_pull_the_payload_once(): void
    {
        $payload = Payload::text('abc123');
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
        $payload = Payload::text('abc123');

        $this->assertSame('abc123', $payload->rawPayload());

        $payload->pull();
        $this->assertSame('', $payload->rawPayload());
    }

    public function test_it_has_up_to_date_signature(): void
    {
        $signature = file_get_contents(__DIR__.'/../../agent/build/signature.txt');

        if ($signature === false) {
            throw new RuntimeException('Unable to read signature');
        }

        $signature = substr($signature, 0, 7);

        $this->assertSame($signature, Payload::SIGNATURE);
    }
}
