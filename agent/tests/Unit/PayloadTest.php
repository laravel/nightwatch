<?php

namespace Tests\Unit;

use Laravel\NightwatchAgent\Payload;
use Tests\TestCase;

use function expect;

class PayloadTest extends TestCase
{
    public function test_it_can_create_a_whole_payload_in_one_append_call(): void
    {
        $payload = new Payload;

        $payload->append('10:a1b2c3d:[]');

        expect($payload->length)->toBe(10);
        expect($payload->signature)->toBe('a1b2c3d');
        expect($payload->value)->toBe('[]');
        expect($payload->complete)->toBeTrue();
    }

    public function test_it_can_contain_more_than_one_colon(): void
    {
        $payload = new Payload;

        $payload->append('25:a1b2c3d:[{"t":"request"}]');

        expect($payload->length)->toBe(25);
        expect($payload->value)->toBe('[{"t":"request"}]');
        expect($payload->signature)->toBe('a1b2c3d');
        expect($payload->complete)->toBeTrue();
    }

    public function test_it_can_incrememtally_create_a_completed_payload(): void
    {
        $payload = new Payload;

        $payload->append('10');
        expect($payload->length)->toBeNull();
        expect($payload->signature)->toBe('');
        expect($payload->value)->toBe('10');
        expect($payload->complete)->toBeFalse();

        $payload->append(':');
        expect($payload->length)->toBeNull();
        expect($payload->signature)->toBe('');
        expect($payload->value)->toBe('10:');
        expect($payload->complete)->toBeFalse();

        $payload->append('a1b2c3');
        expect($payload->length)->toBeNull();
        expect($payload->signature)->toBe('');
        expect($payload->value)->toBe('10:a1b2c3');
        expect($payload->complete)->toBeFalse();

        $payload->append('d');
        expect($payload->length)->toBeNull();
        expect($payload->signature)->toBe('');
        expect($payload->value)->toBe('10:a1b2c3d');
        expect($payload->complete)->toBeFalse();

        $payload->append(':');
        expect($payload->length)->toBe(10);
        expect($payload->signature)->toBe('a1b2c3d');
        expect($payload->value)->toBe('');
        expect($payload->complete)->toBeFalse();

        $payload->append('[');
        expect($payload->length)->toBe(10);
        expect($payload->signature)->toBe('a1b2c3d');
        expect($payload->value)->toBe('[');
        expect($payload->complete)->toBeFalse();

        $payload->append(']');
        expect($payload->length)->toBe(10);
        expect($payload->signature)->toBe('a1b2c3d');
        expect($payload->value)->toBe('[]');
        expect($payload->complete)->toBeTrue();
    }

    public function test_it_is_not_completed_when_it_contains_too_much_data(): void
    {
        $payload = new Payload;

        $payload->append('2:a1b2c3d4:[{}]');

        expect($payload->length)->toBe(2);
        expect($payload->value)->toBe('[{}]');
        expect($payload->complete)->toBeFalse();
    }

    public function test_it_it_can_ingest_empty_strings(): void
    {
        $payload = new Payload;

        $payload->append('');

        expect($payload->length)->toBeNull();
        expect($payload->value)->toBe('');
        expect($payload->complete)->toBeFalse();
    }

    public function test_it_can_have_a_signature_of_any_length(): void
    {
        $payload = new Payload;

        $payload->append('19:1234567890abcdef:[]');

        expect($payload->length)->toBe(19);
        expect($payload->value)->toBe('[]');
        expect($payload->complete)->toBeTrue();
    }
}
