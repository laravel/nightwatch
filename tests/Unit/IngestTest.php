<?php

use Illuminate\Support\Collection;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Payload;
use Tests\FakeRecord;

beforeEach(function () {
    StreamWrapper::reset();
    stream_wrapper_register('tcp', StreamWrapper::class);
    nightwatch()->ingest->streamFactory = fn ($address, $timeout) => fopen($address, 'r+');
});

afterEach(function () {
    stream_wrapper_unregister('tcp');
});

it('configures the stream', function () {
    $calls = [];
    nightwatch()->ingest->streamFactory = function (...$args) use (&$calls) {
        $calls[] = $args;

        return fopen($args[0], 'r+');
    };

    nightwatch()->ingest->write(new FakeRecord);
    nightwatch()->digest();

    expect($calls)->toHaveCount(1);
    [$address, $connectionTimeout] = $calls[0];
    expect($address)->toBe('tcp://127.0.0.1:2407');
    expect($connectionTimeout)->toBe(0.5);
    expect(StreamWrapper::$events->pluck('type')->all())->toBe([
        'stream_open',
        'stream_set_option',
        'stream_write',
        'stream_read',
        'stream_eof',
        'stream_flush',
        'stream_close',
    ]);
});

it('throws an exception when unable to set read timeout', function () {
    $exceptions = [];
    Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$exceptions) {
        $exceptions[] = $e;
    });
    StreamWrapper::intercept('stream_set_option', fn () => false);

    nightwatch()->ingest->write(new FakeRecord);
    nightwatch()->digest();

    expect($exceptions)->toHaveCount(1);
    throw $exceptions[0];
})->throws(RuntimeException::class, <<<'MESSAGE'
Failed configuring agent read timeout

Timed out: false
EOF: false
Blocked: true
URI: tcp://127.0.0.1:2407
Unread bytes: 0
MESSAGE);

it('sets the read timeout', function () {
    nightwatch()->ingest->write(new FakeRecord);
    nightwatch()->digest();

    expect(StreamWrapper::type('stream_set_option'))->toHaveCount(1);
    expect(StreamWrapper::type('stream_set_option')->value('args'))->toBe([
        STREAM_OPTION_READ_TIMEOUT, 0, 500000,
    ]);
    expect(StreamWrapper::$events->pluck('type')->all())->toBe([
        'stream_open',
        'stream_set_option',
        'stream_write',
        'stream_read',
        'stream_eof',
        'stream_flush',
        'stream_close',
    ]);
});

it('can write the payload in one write', function () {
    StreamWrapper::intercept('stream_write', fn (string $value) => 32);

    nightwatch()->ingest->write(new FakeRecord);
    nightwatch()->digest();

    expect(StreamWrapper::type('stream_write'))->toHaveCount(1);
    expect(StreamWrapper::type('stream_write')->value('args'))->toBe([
        '29:'.Payload::SIGNATURE.':[{"t":"fake-record"}]',
    ]);
    expect(StreamWrapper::$events->pluck('type')->all())->toBe([
        'stream_open',
        'stream_set_option',
        'stream_write',
        'stream_read',
        'stream_eof',
        'stream_flush',
        'stream_close',
    ]);
});

it('throws an exception if initial write to stream fails', function () {
    $exceptions = [];
    Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$exceptions) {
        $exceptions[] = $e;
    });
    StreamWrapper::intercept('stream_write', fn (string $value) => false);

    nightwatch()->ingest->write(new FakeRecord);
    nightwatch()->digest();

    expect($exceptions)->toHaveCount(1);
    throw $exceptions[0];
})->throws(RuntimeException::class, <<<'MESSAGE'
Unable to write to the agent. Written [0] Expected [32]

Timed out: false
EOF: false
Blocked: true
URI: tcp://127.0.0.1:2407
Unread bytes: 0
MESSAGE);

it('can write the payload in multiple write', function () {
    $writes = [3, 7, 3, 5, 14];
    StreamWrapper::intercept('stream_write', function (string $value) use (&$writes) {
        return array_shift($writes);
    });

    nightwatch()->ingest->write(new FakeRecord);
    nightwatch()->digest();

    expect(StreamWrapper::type('stream_write'))->toHaveCount(5);
    expect(StreamWrapper::type('stream_write')->pluck('args')->all())->toBe([
        ['29:'.Payload::SIGNATURE.':[{"t":"fake-record"}]'],
        [Payload::SIGNATURE.':[{"t":"fake-record"}]'],
        [':[{"t":"fake-record"}]'],
        ['"t":"fake-record"}]'],
        ['fake-record"}]'],
    ]);
    expect(StreamWrapper::$events->pluck('type')->all())->toBe([
        'stream_open',
        'stream_set_option',
        'stream_write',
        'stream_write',
        'stream_write',
        'stream_write',
        'stream_write',
        'stream_read',
        'stream_eof',
        'stream_flush',
        'stream_close',
    ]);
});

it('throws an exception if subsequent writes to stream fails', function () {
    $exceptions = [];
    Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$exceptions) {
        $exceptions[] = $e;
    });
    $writes = 0;
    StreamWrapper::intercept('stream_write', function (string $value) use (&$writes) {
        if ($writes === 2) {
            return false;
        }

        $writes++;

        return 3;
    });

    nightwatch()->ingest->write(new FakeRecord);
    nightwatch()->digest();

    expect($exceptions)->toHaveCount(1);
    throw $exceptions[0];
})->throws(RuntimeException::class, <<<'MESSAGE'
Unable to write to the agent. Written [6] Expected [32]

Timed out: false
EOF: false
Blocked: true
URI: tcp://127.0.0.1:2407
Unread bytes: 0
MESSAGE);

it('reads response from stream', function () {
    nightwatch()->ingest->write(new FakeRecord);
    nightwatch()->digest();

    expect(StreamWrapper::type('stream_read'))->toHaveCount(1);
    expect(StreamWrapper::type('stream_read')->value('args'))->toBe([
        8192,
    ]);
    expect(StreamWrapper::$events->pluck('type')->all())->toBe([
        'stream_open',
        'stream_set_option',
        'stream_write',
        'stream_read',
        'stream_eof',
        'stream_flush',
        'stream_close',
    ]);
});

it('can read multiple times from stream', function () {
    $response = ['2', ':', 'O', 'K'];
    StreamWrapper::intercept('stream_read', function () use (&$response) {
        return array_shift($response);
    });

    nightwatch()->ingest->write(new FakeRecord);
    nightwatch()->digest();

    expect(StreamWrapper::type('stream_read'))->toHaveCount(4);
    expect(StreamWrapper::type('stream_read')->pluck('args')->all())->toBe([
        [8192],
        [8192],
        [8192],
        [8192],
    ]);
    expect(StreamWrapper::$events->pluck('type')->all())->toBe([
        'stream_open',
        'stream_set_option',
        'stream_write',
        'stream_read',
        'stream_eof',
        'stream_eof',
        'stream_read',
        'stream_eof',
        'stream_eof',
        'stream_read',
        'stream_eof',
        'stream_eof',
        'stream_read',
        'stream_eof',
        'stream_flush',
        'stream_close',
    ]);
});

it('throws an exception if stream EOFs before getting the expected response', function () {
    $exceptions = [];
    Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$exceptions) {
        $exceptions[] = $e;
    });
    $response = ['2', ':', false];
    StreamWrapper::intercept('stream_read', function () use (&$response) {
        if ($response === [':']) {
            StreamWrapper::intercept('stream_eof', fn () => true);
        }

        return array_shift($response);
    });

    nightwatch()->ingest->write(new FakeRecord);
    nightwatch()->digest();

    expect($exceptions)->toHaveCount(1);
    throw $exceptions[0];
})->throws(RuntimeException::class, <<<'MESSAGE'
Failed reading from the agent

Timed out: false
EOF: false
Blocked: true
URI: tcp://127.0.0.1:2407
Unread bytes: 0
MESSAGE);

it('throws when an unexpected response is received', function () {
    $exceptions = [];
    Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$exceptions) {
        $exceptions[] = $e;
    });
    StreamWrapper::intercept('stream_read', fn () => 'XXXXXXXXXXXXXXXXXXXXXXX');

    nightwatch()->ingest->write(new FakeRecord);
    nightwatch()->digest();

    expect($exceptions)->toHaveCount(1);
    throw $exceptions[0];
})->throws(RuntimeException::class, <<<'MESSAGE'
Unexpected response from agent [XXXX]

Timed out: false
EOF: false
Blocked: true
URI: tcp://127.0.0.1:2407
Unread bytes: 19
MESSAGE);

it('closes the stream', function () {
    nightwatch()->ingest->write(new FakeRecord);
    nightwatch()->digest();

    expect(StreamWrapper::$events->pluck('type')->last())->toBe('stream_close');
});

it('does not retrieve meta of already closed stream', function () {
    $exceptions = [];
    Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$exceptions) {
        $exceptions[] = $e;
    });
    $stream = null;
    nightwatch()->ingest->streamFactory = function ($address, $timeout) use (&$stream) {
        $stream = fopen($address, 'r+');

        return $stream;
    };

    StreamWrapper::intercept('stream_read', function () use (&$stream) {
        fclose($stream);

        return false;
    });

    nightwatch()->ingest->write(new FakeRecord);
    nightwatch()->digest();

    expect($exceptions)->toHaveCount(1);
    throw $exceptions[0];
})->throws(RuntimeException::class, <<<'MESSAGE'
Failed reading from the agent

Stream already closed
MESSAGE);

it('stops attempting to read once the stream has reached eof', function () {
    $exceptions = [];
    Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$exceptions) {
        $exceptions[] = $e;
    });
    $reads = 0;
    StreamWrapper::intercept('stream_read', function () use (&$reads) {
        $reads++;

        if ($reads > 2) {
            StreamWrapper::intercept('stream_eof', fn () => true);
        }

        return '';
    });

    nightwatch()->ingest->write(new FakeRecord);
    nightwatch()->digest();

    expect($exceptions)->toHaveCount(1);
    expect($exceptions[0])->toBeInstanceOf(RuntimeException::class);
    expect($exceptions[0]->getMessage())->toBe(<<<'MESSAGE'
    Unexpected response from agent []

    Timed out: false
    EOF: true
    Blocked: true
    URI: tcp://127.0.0.1:2407
    Unread bytes: 0
    MESSAGE);
    expect($reads)->toBe(3);
    expect(StreamWrapper::$events->pluck('type')->all())->toBe([
        'stream_open',
        'stream_set_option',
        'stream_write',
        'stream_read',
        'stream_eof',
        'stream_eof',
        'stream_read',
        'stream_eof',
        'stream_eof',
        'stream_read',
        'stream_eof',
        'stream_flush',
        'stream_close',
    ]);
});

it('only attempts to read from the stream 5 times', function () {
    $exceptions = [];
    Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$exceptions) {
        $exceptions[] = $e;
    });
    $reads = 0;
    StreamWrapper::intercept('stream_read', function () use (&$reads) {
        $reads++;

        return '';
    });

    nightwatch()->ingest->write(new FakeRecord);
    nightwatch()->digest();

    expect($reads)->toBe(5);
    expect($exceptions)->toHaveCount(1);
    expect($exceptions[0])->toBeInstanceOf(RuntimeException::class);
    expect($exceptions[0]->getMessage())->toBe(<<<'MESSAGE'
    Unexpected response from agent []

    Timed out: false
    EOF: false
    Blocked: true
    URI: tcp://127.0.0.1:2407
    Unread bytes: 0
    MESSAGE);
    expect(StreamWrapper::$events->pluck('type')->all())->toBe([
        'stream_open',
        'stream_set_option',
        'stream_write',
        'stream_read',
        'stream_eof',
        'stream_eof',
        'stream_read',
        'stream_eof',
        'stream_eof',
        'stream_read',
        'stream_eof',
        'stream_eof',
        'stream_read',
        'stream_eof',
        'stream_eof',
        'stream_read',
        'stream_eof',
        'stream_eof',
        'stream_eof',
        'stream_flush',
        'stream_close',
    ]);
});

it('does not trigger ingest before reaching threshold', function () {
    $writes = [];
    StreamWrapper::intercept('stream_write', function (string $value) use (&$writes) {
        $writes[] = $value;

        return strlen($value);
    });

    for ($i = 0; $i < 499; $i++) {
        nightwatch()->ingest->write(new FakeRecord);
    }

    expect($writes)->toHaveCount(0);
});

it('triggers ingest after exceeding threshold', function () {
    $writes = [];
    StreamWrapper::intercept('stream_write', function (string $value) use (&$writes) {
        $writes[] = $value;

        return strlen($value);
    });

    for ($i = 0; $i < 499; $i++) {
        nightwatch()->ingest->write(new FakeRecord);
    }

    expect($writes)->toHaveCount(0);

    nightwatch()->ingest->write(new FakeRecord);

    expect($writes)->toHaveCount(2);
    expect(implode('', $writes))->toBe('10009:'.Payload::SIGNATURE.':['.implode(',', array_fill(0, 500, json_encode(new FakeRecord))).']');

    for ($i = 0; $i < 499; $i++) {
        nightwatch()->ingest->write(new FakeRecord);
    }

    expect($writes)->toHaveCount(2);

    nightwatch()->ingest->write(new FakeRecord);

    expect($writes)->toHaveCount(4);
    expect(implode('', $writes))->toBe(str_repeat('10009:'.Payload::SIGNATURE.':['.implode(',', array_fill(0, 500, json_encode(new FakeRecord))).']', 2));
});

class StreamWrapper
{
    public $context;

    private static array $on = [];

    public static Collection $events;

    public function __call(string $name, array $arguments)
    {
        if (! array_key_exists($name, static::$on)) {
            throw new RuntimeException("StreamFake method not implemented [{$name}]");
        }

        static::$events[] = [
            'type' => $name,
            'args' => $arguments,
        ];

        return call_user_func_array(static::$on[$name], $arguments);
    }

    public static function intercept(string $method, callable $callback)
    {
        static::$on[$method] = $callback;
    }

    public static function type(string $type): Collection
    {
        return static::$events->where('type', $type);
    }

    public static function reset()
    {
        static::$events = new Collection;

        static::$on = [
            'stream_open' => fn (string $path, string $mode, int $options, ?string &$openedPath): bool => true,
            'stream_set_option' => fn (int $option, int $arg1, int $arg2): bool => true,
            'stream_write' => fn (string $value): int => strlen($value),
            'stream_read' => fn (int $length): string|false => '2:OK',
            'stream_eof' => fn (): bool => false,
            'stream_flush' => fn (): bool => true,
            'stream_close' => function (): void {
                //
            },
        ];
    }
}
