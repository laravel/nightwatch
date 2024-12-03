<?php

namespace Laravel\Nightwatch;

/**
 * @internal
 */
final class NullUserProvider
{
    public function id(): string
    {
        return '';
    }
}
