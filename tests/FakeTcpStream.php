<?php

namespace Tests;

use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert;
use RuntimeException;

use function call_user_func_array;
use function is_string;
use function strlen;

class FakeTcpStream
{
    private static ?Collection $instances = null;

    public $context;

    public string $value = '';

    public function __construct()
    {
        self::instances()->push($this);
    }

    public static function instances(): Collection
    {
        return self::$instances ??= new Collection;
    }

    /**
     * @param  array<mixed>  $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        $handler = match ($name) {
            'stream_open' => fn (string $path, string $mode, int $options, ?string &$openedPath): bool => true,
            'stream_set_option' => fn (int $option, int $arg1, int $arg2): bool => true,
            'stream_write' => function (string $value): int {
                $this->value .= $value;

                return strlen($value);
            },
            'stream_read' => fn (int $length): string|false => '2:OK',
            'stream_eof' => fn (): bool => false,
            'stream_flush' => fn (): bool => true,
            'stream_close' => function (): void {
                //
            },
            'stream_seek' => fn () => 0,
            default => throw new RuntimeException("FakeTcpStream method not implemented [{$name}]"),
        };

        return call_user_func_array($handler, $arguments);
    }

    public function assertWritten(string|callable $value): self
    {
        if (is_string($value)) {
            Assert::assertSame($value, $this->value);
        } else {
            Assert::assertTrue($value($this->value));
        }

        return $this;
    }

    public static function flush(): void
    {
        self::$instances = null;
    }
}
