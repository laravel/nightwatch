<?php

namespace Laravel\Nightwatch;

use Laravel\Nightwatch\Contracts\TraceIdProvider;

final class TraceId
{
    public function __construct(private string $traceId) {
        //
    }

    public function value(): string
    {
        return $this->traceId;
    }
}
