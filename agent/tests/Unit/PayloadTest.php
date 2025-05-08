<?php

use Laravel\NightwatchAgent\Payload;

it('can create a whole payload in one append call', function () {
    $payload = new Payload;

    $payload->append('10:a1b2c3d:[]');

    expect($payload->length)->toBe(10);
    expect($payload->signature)->toBe('a1b2c3d');
    expect($payload->value)->toBe('[]');
    expect($payload->complete)->toBeTrue();
});

it('can contain more than one colon', function () {
    $payload = new Payload;

    $payload->append('25:a1b2c3d:[{"t":"request"}]');

    expect($payload->length)->toBe(25);
    expect($payload->value)->toBe('[{"t":"request"}]');
    expect($payload->signature)->toBe('a1b2c3d');
    expect($payload->complete)->toBeTrue();
});

it('can incrememtally create a completed payload', function () {
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
});

it('is not completed when it contains too much data', function () {
    $payload = new Payload;

    $payload->append('2:a1b2c3d4:[{}]');

    expect($payload->length)->toBe(2);
    expect($payload->value)->toBe('[{}]');
    expect($payload->complete)->toBeFalse();
});

it('it can ingest empty strings', function () {
    $payload = new Payload;

    $payload->append('');

    expect($payload->length)->toBeNull();
    expect($payload->value)->toBe('');
    expect($payload->complete)->toBeFalse();
});

it('can have a signature of any length', function () {
    $payload = new Payload;

    $payload->append('19:1234567890abcdef:[]');

    expect($payload->length)->toBe(19);
    expect($payload->value)->toBe('[]');
    expect($payload->complete)->toBeTrue();
});
