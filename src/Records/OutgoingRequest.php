<?php

namespace Laravel\Nightwatch\Records;

/**
 * @internal
 */
final class OutgoingRequest
{
    public int $v = 1;
    public string $t = 'outgoing_request';

    /**
     * TODO limit string length
     */
    public function __construct(
        public float $timestamp,
        public string $deploy,
        public string $server,
        public string $group,
        public string $trace_id,
        public string $execution_context,
        public string $execution_id,
        public string $user,
        // --- /
        public string $method,
        public string $scheme,
        public string $host,
        public string $port,
        public string $path,
        public int $duration,
        public ?int $request_size,
        public ?int $response_size,
        public string $status_code,
    ) {
        //
    }
}
