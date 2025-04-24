<?php

use Laravel\NightwatchAgent\StreamBuffer;

it('can read an empty buffer', function () {
    $buffer = new StreamBuffer(100);

    expect($buffer->read())->toBe('{"records":[]}');
});

it('can write and read a single record', function () {
    $buffer = new StreamBuffer(100);

    $buffer->write('[{"id":1}]');

    expect($buffer->read())->toBe('{"records":[{"id":1}]}');
});

it('can write and read two records', function () {
    $buffer = new StreamBuffer(100);

    $buffer->write('[{"id":1}]');
    $buffer->write('[{"id":2}]');

    expect($buffer->read())->toBe('{"records":[{"id":1},{"id":2}]}');
});

it('can write and read many records', function () {
    $buffer = new StreamBuffer(100);

    $buffer->write('[{"id":1}]');
    $buffer->write('[{"id":2}]');
    $buffer->write('[{"id":3}]');
    $buffer->write('[{"id":4}]');

    expect($buffer->read())->toBe('{"records":[{"id":1},{"id":2},{"id":3},{"id":4}]}');
});

it('has not reached threshold without writing', function () {
    $buffer = new StreamBuffer(100);

    expect($buffer->reachedThreshold())->toBeFalse();
});

it('has not reached threshold when under length', function () {
    $buffer = new StreamBuffer(100);

    $buffer->write(str_repeat('a', 99));

    expect($buffer->reachedThreshold())->toBeFalse();
});

it('has reached threshold when at length', function () {
    $buffer = new StreamBuffer(100);

    $buffer->write('['.str_repeat('a', 100).']');

    expect($buffer->reachedThreshold())->toBeTrue();
});

it('has reached threshold when over length', function () {
    $buffer = new StreamBuffer(100);

    $buffer->write('['.str_repeat('a', 101).']');

    expect($buffer->reachedThreshold())->toBeTrue();
});

it('has not reached threshold after reading', function () {
    $buffer = new StreamBuffer(100);

    $buffer->write('['.str_repeat('a', 101).']');
    $buffer->read();

    expect($buffer->reachedThreshold())->toBeFalse();
});

it('empties the buffer while reading', function () {
    $buffer = new StreamBuffer(100);

    $buffer->write('[{"id":1}]');

    expect($buffer->read())->toBe('{"records":[{"id":1}]}');
    expect($buffer->read())->toBe('{"records":[]}');
});
