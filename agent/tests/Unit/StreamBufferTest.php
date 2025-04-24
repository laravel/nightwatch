<?php

use Laravel\NightwatchAgent\StreamBuffer;

it('can pull an empty buffer', function () {
    $buffer = new StreamBuffer(100);

    expect($buffer->pull())->toBe('{"records":[]}');
});

it('can write and pull a single record', function () {
    $buffer = new StreamBuffer(100);

    $buffer->write('[{"id":1}]');

    expect($buffer->pull())->toBe('{"records":[{"id":1}]}');
});

it('can write and pull two records', function () {
    $buffer = new StreamBuffer(100);

    $buffer->write('[{"id":1}]');
    $buffer->write('[{"id":2}]');

    expect($buffer->pull())->toBe('{"records":[{"id":1},{"id":2}]}');
});

it('can write and pull many records', function () {
    $buffer = new StreamBuffer(100);

    $buffer->write('[{"id":1}]');
    $buffer->write('[{"id":2}]');
    $buffer->write('[{"id":3}]');
    $buffer->write('[{"id":4}]');

    expect($buffer->pull())->toBe('{"records":[{"id":1},{"id":2},{"id":3},{"id":4}]}');
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

it('has not reached threshold after pull', function () {
    $buffer = new StreamBuffer(100);

    $buffer->write('['.str_repeat('a', 101).']');
    $buffer->pull();

    expect($buffer->reachedThreshold())->toBeFalse();
});

it('empties the buffer while pulling', function () {
    $buffer = new StreamBuffer(100);

    $buffer->write('[{"id":1}]');

    expect($buffer->pull())->toBe('{"records":[{"id":1}]}');
    expect($buffer->pull())->toBe('{"records":[]}');
});
