<?php

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

use function debug_backtrace;

abstract class TestCase extends BaseTestCase
{
    protected function functionName(): string
    {
        return static::class.'::'.debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, limit: 2)[1]['function'];
    }
}
