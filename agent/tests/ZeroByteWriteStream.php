<?php

namespace Tests;

use function fwrite;

class ZeroByteWriteStream
{
    /**
     * @var resource
     */
    public static $target;

    public static int $zeroByteWrites = 0;

    /**
     * @var resource|null
     */
    public $context;

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return true;
    }

    public function stream_write(string $data): int
    {
        if (self::$zeroByteWrites > 0) {
            self::$zeroByteWrites--;

            return 0;
        }

        return fwrite(self::$target, $data) ?: 0;
    }

    /**
     * @return resource
     */
    public function stream_cast(int $castAs)
    {
        return self::$target;
    }
}
