<?php

namespace Laravel\Nightwatch;

final class TraceId
{
    public function __construct(private string $traceId)
    {
        //
    }

    public function value(): string
    {
        return $this->traceId;
    }
}
