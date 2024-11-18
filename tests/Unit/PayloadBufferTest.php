<?php

use Laravel\Nightwatch\Buffers\PayloadBuffer;

it('can flush an empty buffer', function () {
    $buffer = new PayloadBuffer(100);

    expect($buffer->flush())->toBe('{"records":[]}');
});

it('can write and flush a single record', function () {
    $buffer = new PayloadBuffer(100);

    $buffer->write('{"request":{"id":1}}');

    expect($buffer->flush())->toBe('{"records":[{"request":{"id":1}}]}');
});

it('can write and flush two records', function () {
    $buffer = new PayloadBuffer(100);

    $buffer->write('{"request":{"id":1}}');
    $buffer->write('{"request":{"id":2}}');

    expect($buffer->flush())->toBe('{"records":[{"request":{"id":1}},{"request":{"id":2}}]}');
});

it('can write and flush many records', function () {
    $buffer = new PayloadBuffer(100);

    $buffer->write('{"request":{"id":1}}');
    $buffer->write('{"request":{"id":2}}');
    $buffer->write('{"request":{"id":3}}');
    $buffer->write('{"request":{"id":4}}');

    expect($buffer->flush())->toBe('{"records":[{"request":{"id":1}},{"request":{"id":2}},{"request":{"id":3}},{"request":{"id":4}}]}');
});

it('does does not want flushing without writes', function () {
    $buffer = new PayloadBuffer(100);

    $this->assertFalse($buffer->wantsFlushing());
});

it('does not want flushing before reaching the threshold', function () {
    $buffer = new PayloadBuffer(100);

    $buffer->write(str_repeat('a', 99));

    expect($buffer->wantsFlushing())->toBeFalse();
});

it('wants flushing once the thresold has been reached', function () {
    $buffer = new PayloadBuffer(100);

    $buffer->write(str_repeat('a', 100));

    expect($buffer->wantsFlushing())->toBeTrue();
});

it('wants flushing once the thresold has been exceeded', function () {
    $buffer = new PayloadBuffer(100);

    $buffer->write(str_repeat('a', 101));

    expect($buffer->wantsFlushing())->toBeTrue();
});

it('does does not want flushing after flushed', function () {
    $buffer = new PayloadBuffer(100);

    $buffer->write(str_repeat('a', 101));
    $buffer->flush();

    expect($buffer->wantsFlushing())->toBeFalse();
});

it('empties the buffer while flushing', function () {
    $buffer = new PayloadBuffer(100);

    $buffer->write('{"request":{"id":1}}');

    expect($buffer->flush())->toBe('{"records":[{"request":{"id":1}}]}');
    expect($buffer->flush())->toBe('{"records":[]}');
});
