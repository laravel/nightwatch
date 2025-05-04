<?php

use Laravel\Nightwatch\Payload;

it('can determine if a JSON payload is empty', function ($value, $empty) {
    $payload = Payload::json(json_encode($value, flags: JSON_THROW_ON_ERROR));

    expect($payload->isEmpty())->toBe($empty);
})->with([
    [null, true],
    [true, false],
    [false, false],
    [0, false],
    [1, false],
    [-1, false],
    ['', true],
    [' ', false],
    ['a', false],
    [[], true],
    [[1], false],
    [(object) [], true],
    [(object) ['a' => 1], false],
]);

it('can determine if a TEXT payload is empty', function ($value, $empty) {
    $payload = Payload::text($value);

    expect($payload->isEmpty())->toBe($empty);
})->with([
    ['', true],
    [' ', false],
    ['a', false],
]);

it('can pull the bencoded signed value', function () {
    $payload = Payload::text('abc123');
    $encoded = $payload->pull();

    expect($encoded)->toBe('14:'.Payload::SIGNATURE.':abc123');
});

it('can only pull the payload once', function () {
    $payload = Payload::text('abc123');
    $payload->pull();

    try {
        $payload->pull();
        throw new RuntimeException;
    } catch (Throwable $e) {
        expect($e->getMessage())->toBe('Payload has already been read');
    }
});

it('frees memory after pulling the payload', function () {
    $payload = Payload::text('abc123');

    expect($payload->rawPayload())->toBe('abc123');

    $payload->pull();
    expect($payload->rawPayload())->toBe('');
});

it('has up-to-date signature', function () {
    $signature = file_get_contents(__DIR__.'/../../agent/build/signature.txt');

    if ($signature === false) {
        throw new RuntimeException('Unable to read signature');
    }

    $signature = substr($signature, 0, 7);

    expect(Payload::SIGNATURE)->toBe($signature);
});
