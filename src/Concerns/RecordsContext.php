<?php

namespace Laravel\Nightwatch\Concerns;

use Illuminate\Support\Facades\Context;
use Laravel\Nightwatch\Compatibility;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Types\Str;
use Throwable;

use function json_encode;

/**
 * @internal
 */
trait RecordsContext
{
    private function serializedContext(): string
    {
        if (! Compatibility::$contextExists) {
            return '';
        }

        try {
            return Str::text(json_encode((object) Context::all(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
        } catch (Throwable $e) {
            Nightwatch::unrecoverableExceptionOccurred($e);

            return '{"_nightwatch_error":"Failed to serialize context"}';
        }
    }
}
