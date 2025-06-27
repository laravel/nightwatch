<?php

namespace Laravel\Nightwatch\Records;

final class OutgoingRequest
{
    public function __construct(
        public readonly string $method,
        public string $url,
        public readonly int $duration,
        public readonly int $requestSize,
        public readonly int $responseSize,
        public readonly int $statusCode,
    ) {
        //
    }
}
