<?php

use Laravel\NightwatchAgent\Payload;

it('can create a whole payload in one append call', function () {
    $payload = new Payload;

    $payload->append('2:[]');

    expect($payload->length)->toBe(2);
    expect($payload->value)->toBe('[]');
    expect($payload->complete)->toBeTrue();
});

it('can contain more than one colon', function () {
    $payload = new Payload;

    $payload->append('17:[{"t":"request"}]');

    expect($payload->length)->toBe(17);
    expect($payload->value)->toBe('[{"t":"request"}]');
    expect($payload->complete)->toBeTrue();
});

it('can incrememtally create a completed payload', function () {
    $payload = new Payload;

    $payload->append('2');
    expect($payload->length)->toBeNull();
    expect($payload->value)->toBe('2');
    expect($payload->complete)->toBeFalse();

    $payload->append(':');
    expect($payload->length)->toBe(2);
    expect($payload->value)->toBe('');
    expect($payload->complete)->toBeFalse();

    $payload->append('[');
    expect($payload->length)->toBe(2);
    expect($payload->value)->toBe('[');
    expect($payload->complete)->toBeFalse();

    $payload->append(']');
    expect($payload->length)->toBe(2);
    expect($payload->value)->toBe('[]');
    expect($payload->complete)->toBeTrue();
});

it('is not completed when it contains too much data', function () {
    $payload = new Payload;

    $payload->append('2:[{}]');

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
