<?php

beforeAll(function () {
    stream_wrapper_register('tcp', StreamFake::class);
});

beforeEach(function () {
    StreamFake::reset();
});

it('throws an exception when unable to set timeout', function () {
    nightwatch()->ingest->streamFactory = fn ($address, $timeout) => fopen($address, 'r+');

    StreamFake::intercept('stream_set_option', fn () => false);

    nightwatch()->ingest->write('[{}]');
})->throws(RuntimeException::class, <<<'MESSAGE'
Failed configuring agent write timeout

Timed out: false
EOF: false
Blocked: true
URI: tcp://127.0.0.1:2407
Unread bytes: 0
MESSAGE);

it('shows correct eof status in exception meta', function () {
    nightwatch()->ingest->streamFactory = fn ($address, $timeout) => fopen($address, 'r+');

    StreamFake::intercept('stream_set_option', fn () => false);
    StreamFake::intercept('stream_eof', fn () => true);

    nightwatch()->ingest->write('[{}]');
})->throws(RuntimeException::class, <<<'MESSAGE'
Failed configuring agent write timeout

Timed out: false
EOF: true
Blocked: true
URI: tcp://127.0.0.1:2407
Unread bytes: 0
MESSAGE);

it('sets the wait timeout', function () {
    nightwatch()->ingest->streamFactory = fn ($address, $timeout) => fopen($address, 'r+');

    $calls = [];
    StreamFake::intercept('stream_set_option', function (...$args) use (&$calls) {
        $calls[] = $args;

        return true;
    });

    nightwatch()->ingest->write('[{}]');

    expect($calls)->toHaveCount(1);
    [$option, $second, $microseconds] = $calls[0];
    expect($option)->toBe(STREAM_OPTION_READ_TIMEOUT);
    expect($second)->toBe(0);
    expect($microseconds)->toBe(500000);
});

class StreamFake
{
    protected static $on = [];

    public $context;

    public function __call(string $name, array $arguments)
    {
        if (! array_key_exists($name, static::$on)) {
            throw new RuntimeException("StreamFake method not implemented [{$name}]");
        }

        return call_user_func_array(static::$on[$name], $arguments);
    }

    public static function intercept(string $method, callable $callback)
    {
        static::$on[$method] = $callback;
    }

    public static function reset()
    {
        static::$on = [
            'stream_open' => fn () => true,
            'stream_set_option' => fn () => true,
            'stream_eof' => fn () => false,
            'stream_close' => fn () => true,
            'stream_flush' => fn () => true,
            'stream_write' => fn () => true,
            'stream_read' => fn () => true,
        ];
    }
}
