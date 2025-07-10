<?php

namespace Tests\Unit;

use Laravel\NightwatchAgent\Payload;
use Tests\TestCase;

class PayloadTest extends TestCase
{
    public function test_it_can_create_a_whole_payload_in_one_append_call(): void
    {
        $payload = new Payload;

        $payload->append('13:v1:a1b2c3d:[]');

        $this->assertSame(13, $payload->length);
        $this->assertSame('v1', $payload->version);
        $this->assertSame('a1b2c3d', $payload->tokenHash);
        $this->assertSame('[]', $payload->value);
        $this->assertTrue($payload->complete);
    }

    public function test_it_can_contain_more_than_one_colon(): void
    {
        $payload = new Payload;

        $payload->append('28:v1:a1b2c3d:[{"t":"request"}]');

        $this->assertSame(28, $payload->length);
        $this->assertSame('[{"t":"request"}]', $payload->value);
        $this->assertSame('v1', $payload->version);
        $this->assertSame('a1b2c3d', $payload->tokenHash);
        $this->assertTrue($payload->complete);
    }

    public function test_it_can_incrememtally_create_a_completed_payload(): void
    {
        $payload = new Payload;

        $payload->append('13');
        $this->assertNull($payload->length);
        $this->assertSame('', $payload->version);
        $this->assertSame('', $payload->tokenHash);
        $this->assertSame('13', $payload->value);
        $this->assertFalse($payload->complete);

        $payload->append(':');
        $this->assertNull($payload->length);
        $this->assertSame('', $payload->version);
        $this->assertSame('', $payload->tokenHash);
        $this->assertSame('13:', $payload->value);
        $this->assertFalse($payload->complete);

        $payload->append('v1');
        $this->assertNull($payload->length);
        $this->assertSame('', $payload->version);
        $this->assertSame('', $payload->tokenHash);
        $this->assertSame('13:v1', $payload->value);
        $this->assertFalse($payload->complete);

        $payload->append(':');
        $this->assertNull($payload->length);
        $this->assertSame('', $payload->version);
        $this->assertSame('', $payload->tokenHash);
        $this->assertSame('13:v1:', $payload->value);
        $this->assertFalse($payload->complete);

        $payload->append('a1b2c3');
        $this->assertNull($payload->length);
        $this->assertSame('', $payload->version);
        $this->assertSame('', $payload->tokenHash);
        $this->assertSame('13:v1:a1b2c3', $payload->value);
        $this->assertFalse($payload->complete);

        $payload->append('d');
        $this->assertNull($payload->length);
        $this->assertSame('', $payload->version);
        $this->assertSame('', $payload->tokenHash);
        $this->assertSame('13:v1:a1b2c3d', $payload->value);
        $this->assertFalse($payload->complete);

        $payload->append(':');
        $this->assertSame(13, $payload->length);
        $this->assertSame('v1', $payload->version);
        $this->assertSame('a1b2c3d', $payload->tokenHash);
        $this->assertSame('', $payload->value);
        $this->assertFalse($payload->complete);

        $payload->append('[');
        $this->assertSame(13, $payload->length);
        $this->assertSame('v1', $payload->version);
        $this->assertSame('a1b2c3d', $payload->tokenHash);
        $this->assertSame('[', $payload->value);
        $this->assertFalse($payload->complete);

        $payload->append(']');
        $this->assertSame(13, $payload->length);
        $this->assertSame('v1', $payload->version);
        $this->assertSame('a1b2c3d', $payload->tokenHash);
        $this->assertSame('[]', $payload->value);
        $this->assertTrue($payload->complete);
    }

    public function test_it_is_not_completed_when_it_contains_too_much_data(): void
    {
        $payload = new Payload;

        $payload->append('2:v1:a1b2c3d4:[{}]');

        $this->assertSame(2, $payload->length);
        $this->assertSame('[{}]', $payload->value);
        $this->assertFalse($payload->complete);
    }

    public function test_it_can_ingest_empty_strings(): void
    {
        $payload = new Payload;

        $payload->append('');

        $this->assertNull($payload->length);
        $this->assertSame('', $payload->value);
        $this->assertFalse($payload->complete);
    }

    public function test_it_can_have_a_token_hash_of_any_length(): void
    {
        $payload = new Payload;

        $payload->append('22:v1:1234567890abcdef:[]');

        $this->assertSame(22, $payload->length);
        $this->assertSame('[]', $payload->value);
        $this->assertTrue($payload->complete);
    }
}
