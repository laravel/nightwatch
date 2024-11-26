<?php

use Laravel\Nightwatch\Buffers\StreamBuffer;

it('can flush an empty buffer', function () {
    $buffer = new StreamBuffer(100);

    expect($buffer->flush())->toBe('{"records":[]}');
});

it('can write and flush a single record', function () {
    $buffer = new StreamBuffer(100);

    $buffer->write('[{"id":1}]');

    expect($buffer->flush())->toBe('{"records":[{"id":1}]}');
});

it('can write and flush two records', function () {
    $buffer = new StreamBuffer(100);

    $buffer->write('[{"id":1}]');
    $buffer->write('[{"id":2}]');

    expect($buffer->flush())->toBe('{"records":[{"id":1},{"id":2}]}');
});

it('can write and flush many records', function () {
    $buffer = new StreamBuffer(100);

    $buffer->write('[{"id":1}]');
    $buffer->write('[{"id":2}]');
    $buffer->write('[{"id":3}]');
    $buffer->write('[{"id":4}]');

    expect($buffer->flush())->toBe('{"records":[{"id":1},{"id":2},{"id":3},{"id":4}]}');
});

it('does does not want flushing without writes', function () {
    $buffer = new StreamBuffer(100);

    $this->assertFalse($buffer->wantsFlushing());
});

it('does not want flushing before reaching the threshold', function () {
    $buffer = new StreamBuffer(100);

    $buffer->write(str_repeat('a', 99));

    expect($buffer->wantsFlushing())->toBeFalse();
});

it('wants flushing once the thresold has been reached', function () {
    $buffer = new StreamBuffer(100);

    $buffer->write('['.str_repeat('a', 100).']');

    expect($buffer->wantsFlushing())->toBeTrue();
});

it('wants flushing once the thresold has been exceeded', function () {
    $buffer = new StreamBuffer(100);

    $buffer->write('['.str_repeat('a', 101).']');

    expect($buffer->wantsFlushing())->toBeTrue();
});

it('does does not want flushing after flushed', function () {
    $buffer = new StreamBuffer(100);

    $buffer->write('['.str_repeat('a', 101).']');
    $buffer->flush();

    expect($buffer->wantsFlushing())->toBeFalse();
});

it('empties the buffer while flushing', function () {
    $buffer = new StreamBuffer(100);

    $buffer->write('[{"id":1}]');

    expect($buffer->flush())->toBe('{"records":[{"id":1}]}');
    expect($buffer->flush())->toBe('{"records":[]}');
});
