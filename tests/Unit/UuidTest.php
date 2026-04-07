<?php

namespace Tests\Unit;

use Laravel\Nightwatch\Support\Uuid;
use Ramsey\Uuid\Uuid as BaseUuid;
use Tests\TestCase;

class UuidTest extends TestCase
{
    public function test_it_generates_random_uuid_without_seed(): void
    {
        $uuid = new Uuid(static fn () => BaseUuid::uuid4()->toString());

        $a = $uuid->make();
        $b = $uuid->make();

        $this->assertNotSame($a, $b);
        $this->assertTrue(BaseUuid::isValid($a));
        $this->assertTrue(BaseUuid::isValid($b));
    }

    public function test_it_generates_deterministic_uuid_with_seed(): void
    {
        $uuid = new Uuid(static fn () => BaseUuid::uuid4()->toString());

        $a = $uuid->make('cf-ray-abc123');
        $b = $uuid->make('cf-ray-abc123');

        $this->assertSame($a, $b);
        $this->assertTrue(BaseUuid::isValid($a));
    }

    public function test_different_seeds_produce_different_uuids(): void
    {
        $uuid = new Uuid(static fn () => BaseUuid::uuid4()->toString());

        $a = $uuid->make('cf-ray-abc123');
        $b = $uuid->make('cf-ray-def456');

        $this->assertNotSame($a, $b);
    }

    public function test_it_falls_back_to_resolver_with_null_seed(): void
    {
        $uuid = new Uuid(static fn () => 'fixed-uuid');

        $this->assertSame('fixed-uuid', $uuid->make(null));
    }

    public function test_it_falls_back_to_resolver_with_empty_string_seed(): void
    {
        $uuid = new Uuid(static fn () => 'fixed-uuid');

        $this->assertSame('fixed-uuid', $uuid->make(''));
    }
}
