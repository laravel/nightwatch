<?php

use Laravel\Package\RecordBuffer;

it('can flsuh an empty buffer', function () {
    $buffer = new RecordBuffer(100);

    expect($buffer->flush())->toBe('{"records":[]}');
});

it('can write and flush a single record', function () {
    $buffer = new RecordBuffer(100);

    $buffer->write('{"request":{"id":1}}');

    expect($buffer->flush())->toBe('{"records":[{"request":{"id":1}}]}');
});

it('can write and flush two records', function () {
    $buffer = new RecordBuffer(100);

    $buffer->write('{"request":{"id":1}}');
    $buffer->write('{"request":{"id":2}}');

    expect($buffer->flush())->toBe('{"records":[{"request":{"id":1}},{"request":{"id":2}}]}');
});

it('can write and flush many records', function () {
    $buffer = new RecordBuffer(100);

    $buffer->write('{"request":{"id":1}}');
    $buffer->write('{"request":{"id":2}}');
    $buffer->write('{"request":{"id":3}}');
    $buffer->write('{"request":{"id":4}}');

    expect($buffer->flush())->toBe('{"records":[{"request":{"id":1}},{"request":{"id":2}},{"request":{"id":3}},{"request":{"id":4}}]}');
});

it('ignores empty strings', function () {
    $buffer = new RecordBuffer(100);

    $buffer->write('');
    $buffer->write('{"request":{"id":2}}');
    $buffer->write('');
    $buffer->write('{"request":{"id":4}}');

    expect($buffer->flush())->toBe('{"records":[{"request":{"id":2}},{"request":{"id":4}}]}');
});

it('does not want flushing before reaching the threshold', function () {

});

it('wants flushing once the thresold has been reached', function () {

});

it('wants flushing once the thresold has been exceeded', function () {

});
