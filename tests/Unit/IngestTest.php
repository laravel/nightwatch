<?php

namespace Tests\Unit;

use Illuminate\Support\Collection;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Payload;
use RuntimeException;
use Tests\FakeRecord;
use Tests\TestCase;

use function array_fill;
use function array_key_exists;
use function array_shift;
use function call_user_func_array;
use function fclose;
use function fopen;
use function implode;
use function json_encode;
use function str_repeat;
use function stream_wrapper_register;
use function stream_wrapper_unregister;
use function strlen;

class IngestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        StreamWrapper::reset();
        stream_wrapper_register('tcp', StreamWrapper::class);
        $this->core->ingest->streamFactory = fn ($address, $timeout) => fopen($address, 'r+');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        stream_wrapper_unregister('tcp');
    }

    public function test_it_configures_the_stream(): void
    {
        $calls = [];
        $this->core->ingest->streamFactory = function (...$args) use (&$calls) {
            $calls[] = $args;

            return fopen($args[0], 'r+');
        };

        $this->core->ingest->write(FakeRecord::make());
        $this->core->digest();

        $this->assertCount(1, $calls);
        [$address, $connectionTimeout] = $calls[0];
        $this->assertSame('tcp://127.0.0.1:2407', $address);
        $this->assertSame(0.5, $connectionTimeout);
        $this->assertSame([
            'stream_open',
            'stream_set_option',
            'stream_write',
            'stream_read',
            'stream_eof',
            'stream_flush',
            'stream_close',
        ], StreamWrapper::$events->pluck('type')->all());
    }

    public function test_it_throws_an_exception_when_unable_to_set_read_timeout(): void
    {
        $exceptions = [];
        Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$exceptions): void {
            $exceptions[] = $e;
        });
        StreamWrapper::intercept('stream_set_option', fn () => false);

        $this->core->ingest->write(FakeRecord::make());
        $this->core->digest();

        $this->assertCount(1, $exceptions);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(<<<'MESSAGE'
        Failed configuring agent read timeout

        Timed out: false
        EOF: false
        Blocked: true
        URI: tcp://127.0.0.1:2407
        Unread bytes: 0
        MESSAGE);

        throw $exceptions[0];
    }

    public function test_it_sets_the_read_timeout(): void
    {
        $this->core->ingest->write(FakeRecord::make());
        $this->core->digest();

        $this->assertCount(1, StreamWrapper::type('stream_set_option'));
        $this->assertSame([
            STREAM_OPTION_READ_TIMEOUT, 0, 500000,
        ], StreamWrapper::type('stream_set_option')->value('args'));
        $this->assertSame([
            'stream_open',
            'stream_set_option',
            'stream_write',
            'stream_read',
            'stream_eof',
            'stream_flush',
            'stream_close',
        ], StreamWrapper::$events->pluck('type')->all());
    }

    public function test_it_can_write_the_payload_in_one_write(): void
    {
        $tokenHash = self::tokenHash();
        StreamWrapper::intercept('stream_write', fn (string $value) => 35);

        $this->core->ingest->write(FakeRecord::make());
        $this->core->digest();

        $this->assertCount(1, StreamWrapper::type('stream_write'));
        $this->assertSame([
            '32:'.Payload::PAYLOAD_VERSION.':'.$tokenHash.':[{"t":"fake-record"}]',
        ], StreamWrapper::type('stream_write')->value('args'));
        $this->assertSame([
            'stream_open',
            'stream_set_option',
            'stream_write',
            'stream_read',
            'stream_eof',
            'stream_flush',
            'stream_close',
        ], StreamWrapper::$events->pluck('type')->all());
    }

    public function test_it_throws_an_exception_if_initial_write_to_stream_fails(): void
    {
        $exceptions = [];
        Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$exceptions): void {
            $exceptions[] = $e;
        });
        StreamWrapper::intercept('stream_write', fn (string $value) => false);

        $this->core->ingest->write(FakeRecord::make());
        $this->core->digest();

        $this->assertCount(1, $exceptions);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(<<<'MESSAGE'
        Unable to write to the agent. Written [0] Expected [35]

        Timed out: false
        EOF: false
        Blocked: true
        URI: tcp://127.0.0.1:2407
        Unread bytes: 0
        MESSAGE);

        throw $exceptions[0];
    }

    public function test_it_can_write_the_payload_in_multiple_write(): void
    {
        $tokenHash = self::tokenHash();
        $writes = [3, 2, 8, 3, 5, 14];
        StreamWrapper::intercept('stream_write', function (string $value) use (&$writes) {
            return array_shift($writes);
        });

        $this->core->ingest->write(FakeRecord::make());
        $this->core->digest();

        $this->assertCount(6, StreamWrapper::type('stream_write'));
        $this->assertSame([
            ['32:'.Payload::PAYLOAD_VERSION.':'.$tokenHash.':[{"t":"fake-record"}]'],
            [Payload::PAYLOAD_VERSION.':'.$tokenHash.':[{"t":"fake-record"}]'],
            [':'.$tokenHash.':[{"t":"fake-record"}]'],
            [':[{"t":"fake-record"}]'],
            ['"t":"fake-record"}]'],
            ['fake-record"}]'],
        ], StreamWrapper::type('stream_write')->pluck('args')->all());
        $this->assertSame([
            'stream_open',
            'stream_set_option',
            'stream_write',
            'stream_write',
            'stream_write',
            'stream_write',
            'stream_write',
            'stream_write',
            'stream_read',
            'stream_eof',
            'stream_flush',
            'stream_close',
        ], StreamWrapper::$events->pluck('type')->all());
    }

    public function test_it_throws_an_exception_if_subsequent_writes_to_stream_fails(): void
    {
        $exceptions = [];
        Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$exceptions): void {
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

        $this->core->ingest->write(FakeRecord::make());
        $this->core->digest();

        $this->assertCount(1, $exceptions);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(<<<'MESSAGE'
        Unable to write to the agent. Written [6] Expected [35]

        Timed out: false
        EOF: false
        Blocked: true
        URI: tcp://127.0.0.1:2407
        Unread bytes: 0
        MESSAGE);

        throw $exceptions[0];
    }

    public function test_it_reads_response_from_stream(): void
    {
        $this->core->ingest->write(FakeRecord::make());
        $this->core->digest();

        $this->assertCount(1, StreamWrapper::type('stream_read'));
        $this->assertSame([
            8192,
        ], StreamWrapper::type('stream_read')->value('args'));
        $this->assertSame([
            'stream_open',
            'stream_set_option',
            'stream_write',
            'stream_read',
            'stream_eof',
            'stream_flush',
            'stream_close',
        ], StreamWrapper::$events->pluck('type')->all());
    }

    public function test_it_can_read_multiple_times_from_stream(): void
    {
        $response = ['2', ':', 'O', 'K'];
        StreamWrapper::intercept('stream_read', function () use (&$response) {
            return array_shift($response);
        });

        $this->core->ingest->write(FakeRecord::make());
        $this->core->digest();

        $this->assertCount(4, StreamWrapper::type('stream_read'));
        $this->assertSame([
            [8192],
            [8192],
            [8192],
            [8192],
        ], StreamWrapper::type('stream_read')->pluck('args')->all());
        $this->assertSame([
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
        ], StreamWrapper::$events->pluck('type')->all());
    }

    public function test_it_throws_an_exception_if_stream_eo_fs_before_getting_the_expected_response(): void
    {
        $exceptions = [];
        Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$exceptions): void {
            $exceptions[] = $e;
        });
        $response = ['2', ':', false];
        StreamWrapper::intercept('stream_read', function () use (&$response) {
            if ($response === [':']) {
                StreamWrapper::intercept('stream_eof', fn () => true);
            }

            return array_shift($response);
        });

        $this->core->ingest->write(FakeRecord::make());
        $this->core->digest();

        $this->assertCount(1, $exceptions);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(<<<'MESSAGE'
        Failed reading from the agent

        Timed out: false
        EOF: false
        Blocked: true
        URI: tcp://127.0.0.1:2407
        Unread bytes: 0
        MESSAGE);

        throw $exceptions[0];
    }

    public function test_it_throws_when_an_unexpected_response_is_received(): void
    {
        $exceptions = [];
        Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$exceptions): void {
            $exceptions[] = $e;
        });
        StreamWrapper::intercept('stream_read', fn () => 'XXXXXXXXXXXXXXXXXXXXXXX');

        $this->core->ingest->write(FakeRecord::make());
        $this->core->digest();

        $this->assertCount(1, $exceptions);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(<<<'MESSAGE'
        Unexpected response from agent [XXXX]

        Timed out: false
        EOF: false
        Blocked: true
        URI: tcp://127.0.0.1:2407
        Unread bytes: 19
        MESSAGE);

        throw $exceptions[0];
    }

    public function test_it_closes_the_stream(): void
    {
        $this->core->ingest->write(FakeRecord::make());
        $this->core->digest();

        $this->assertSame('stream_close', StreamWrapper::$events->pluck('type')->last());
    }

    public function test_it_does_not_retrieve_meta_of_already_closed_stream(): void
    {
        $exceptions = [];
        Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$exceptions): void {
            $exceptions[] = $e;
        });
        $stream = null;
        $this->core->ingest->streamFactory = function ($address, $timeout) use (&$stream) {
            $stream = fopen($address, 'r+');

            return $stream;
        };

        StreamWrapper::intercept('stream_read', function () use (&$stream) {
            fclose($stream);

            return false;
        });

        $this->core->ingest->write(FakeRecord::make());
        $this->core->digest();

        $this->assertCount(1, $exceptions);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(<<<'MESSAGE'
        Failed reading from the agent

        Stream already closed
        MESSAGE);

        throw $exceptions[0];
    }

    public function test_it_stops_attempting_to_read_once_the_stream_has_reached_eof(): void
    {
        $exceptions = [];
        Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$exceptions): void {
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

        $this->core->ingest->write(FakeRecord::make());
        $this->core->digest();

        $this->assertCount(1, $exceptions);
        $this->assertInstanceOf(RuntimeException::class, $exceptions[0]);
        $this->assertSame(<<<'MESSAGE'
    Unexpected response from agent []

    Timed out: false
    EOF: true
    Blocked: true
    URI: tcp://127.0.0.1:2407
    Unread bytes: 0
    MESSAGE, $exceptions[0]->getMessage());
        $this->assertSame(3, $reads);
        $this->assertSame([
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
        ], StreamWrapper::$events->pluck('type')->all());
    }

    public function test_it_only_attempts_to_read_from_the_stream_5_times(): void
    {
        $exceptions = [];
        Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$exceptions): void {
            $exceptions[] = $e;
        });
        $reads = 0;
        StreamWrapper::intercept('stream_read', function () use (&$reads) {
            $reads++;

            return '';
        });

        $this->core->ingest->write(FakeRecord::make());
        $this->core->digest();

        $this->assertSame(5, $reads);
        $this->assertCount(1, $exceptions);
        $this->assertInstanceOf(RuntimeException::class, $exceptions[0]);
        $this->assertSame(<<<'MESSAGE'
    Unexpected response from agent []

    Timed out: false
    EOF: false
    Blocked: true
    URI: tcp://127.0.0.1:2407
    Unread bytes: 0
    MESSAGE, $exceptions[0]->getMessage());
        $this->assertSame([
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
        ], StreamWrapper::$events->pluck('type')->all());
    }

    public function test_it_does_not_trigger_ingest_before_reaching_threshold(): void
    {
        $writes = [];
        StreamWrapper::intercept('stream_write', function (string $value) use (&$writes) {
            $writes[] = $value;

            return strlen($value);
        });

        for ($i = 0; $i < 499; $i++) {
            $this->core->ingest->write(FakeRecord::make());
        }

        $this->assertCount(0, $writes);
    }

    public function test_it_triggers_ingest_after_exceeding_threshold(): void
    {
        $tokenHash = self::tokenHash();
        $writes = [];
        StreamWrapper::intercept('stream_write', function (string $value) use (&$writes) {
            $writes[] = $value;

            return strlen($value);
        });

        for ($i = 0; $i < 499; $i++) {
            $this->core->ingest->write(FakeRecord::make());
        }

        $this->assertCount(0, $writes);

        $this->core->ingest->write(FakeRecord::make());

        $this->assertCount(2, $writes);
        $this->assertSame('10012:'.Payload::PAYLOAD_VERSION.':'.$tokenHash.':['.implode(',', array_fill(0, 500, json_encode(FakeRecord::make()))).']', implode('', $writes));

        for ($i = 0; $i < 499; $i++) {
            $this->core->ingest->write(FakeRecord::make());
        }

        $this->assertCount(2, $writes);

        $this->core->ingest->write(FakeRecord::make());

        $this->assertCount(4, $writes);
        $this->assertSame(str_repeat('10012:'.Payload::PAYLOAD_VERSION.':'.$tokenHash.':['.implode(',', array_fill(0, 500, json_encode(FakeRecord::make()))).']', 2), implode('', $writes));
    }
}

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

    public static function intercept(string $method, callable $callback): void
    {
        static::$on[$method] = $callback;
    }

    public static function type(string $type): Collection
    {
        return static::$events->where('type', $type);
    }

    public static function reset(): void
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
