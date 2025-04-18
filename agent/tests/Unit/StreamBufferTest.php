<?php

use Laravel\NightwatchAgent\Payload;
use Laravel\NightwatchAgent\StreamBuffer;

it('can flush an empty buffer', function () {
    $buffer = new StreamBuffer(100);

    expect($buffer->flush())->toBe('{"records":[]}');
});

it('can write and flush a single record', function () {
    $buffer = new StreamBuffer(100);
    $payload = new Payload;

    $payload->append('10:[{"id":1}]');
    $buffer->write($payload);

    expect($buffer->flush())->toBe('{"records":[{"id":1}]}');
});

it('can write and flush two records', function () {
    $buffer = new StreamBuffer(100);

    $payload = new Payload;
    $payload->append('10:[{"id":1}]');
    $buffer->write($payload);

    $payload = new Payload;
    $payload->append('10:[{"id":2}]');
    $buffer->write($payload);

    expect($buffer->flush())->toBe('{"records":[{"id":1},{"id":2}]}');
});

it('can write and flush many records', function () {
    $buffer = new StreamBuffer(100);

    $payload = new Payload;
    $payload->append('10:[{"id":1}]');
    $buffer->write($payload);
    $payload = new Payload;
    $payload->append('10:[{"id":2}]');
    $buffer->write($payload);
    $payload = new Payload;
    $payload->append('10:[{"id":3}]');
    $buffer->write($payload);
    $payload = new Payload;
    $payload->append('10:[{"id":4}]');
    $buffer->write($payload);

    expect($buffer->flush())->toBe('{"records":[{"id":1},{"id":2},{"id":3},{"id":4}]}');
});

it('does does not want flushing without writes', function () {
    $buffer = new StreamBuffer(100);

    expect($buffer->wantsFlushing())->toBeFalse();
});

it('does not want flushing before reaching the threshold', function () {
    $buffer = new StreamBuffer(100);
    $payload = new Payload;

    $payload->append('101:'.str_repeat('a', 101));
    $buffer->write($payload);

    expect($buffer->wantsFlushing())->toBeFalse();
});

it('wants flushing once the thresold has been reached', function () {
    $buffer = new StreamBuffer(100);
    $payload = new Payload;

    $payload->append('102:'.str_repeat('a', 102));
    $buffer->write($payload);

    expect($buffer->wantsFlushing())->toBeTrue();
});

it('wants flushing once the thresold has been exceeded', function () {
    $buffer = new StreamBuffer(100);
    $payload = new Payload;

    $payload->append('103:'.str_repeat('a', 103));
    $buffer->write($payload);

    expect($buffer->wantsFlushing())->toBeTrue();
});

it('does does not want flushing after flushed', function () {
    $buffer = new StreamBuffer(100);
    $payload = new Payload;

    $payload->append('10:[{"id":1}]');
    $buffer->write($payload);
    $buffer->flush();

    expect($buffer->wantsFlushing())->toBeFalse();
});

it('empties the buffer while flushing', function () {
    $buffer = new StreamBuffer(100);
    $payload = new Payload;

    $payload->append('10:[{"id":1}]');
    $buffer->write($payload);

    expect($buffer->flush())->toBe('{"records":[{"id":1}]}');
    expect($buffer->flush())->toBe('{"records":[]}');
});
